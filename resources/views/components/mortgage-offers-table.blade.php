@php
    use App\Models\MortgageOffer;

    // Only one category is supported so far ('secondary_market' - buying an
    // existing home) since banks split mortgages into incompatible product
    // types (new-construction, renovation, government programs, ...) and
    // comparing across those wouldn't be meaningful. See MortgageOffer
    // parsers for how each bank's products were mapped to this category.
    $category = 'secondary_market';

    $preferredCurrencyOrder = ['AMD', 'USD', 'EUR', 'GBP', 'CHF', 'RUR', 'GEL'];

    // Precomputed once (instead of per-row) so displaying a rating badge
    // next to each organization doesn't add an N+1 query per row.
    $ratingsByOrgId = \App\Models\Organization::withRatingStats()->get()->keyBy('id');

    // Rough starting points per currency so the calculator shows a sensible
    // result before the user changes anything.
    $defaultPropertyPrice = ['AMD' => 30000000, 'USD' => 80000, 'EUR' => 70000];

    $availableCurrencies = MortgageOffer::query()
        ->where('category', $category)
        ->whereHas('organization', fn ($query) => $query->active())
        ->select('currency')
        ->distinct()
        ->pluck('currency')
        ->sortBy(fn ($currency) => array_search($currency, $preferredCurrencyOrder) !== false
            ? array_search($currency, $preferredCurrencyOrder)
            : count($preferredCurrencyOrder))
        ->values();

    // Every qualifying row is embedded (not pre-ranked) because eligibility
    // and the resulting monthly payment depend on the property price/down
    // payment/term the user picks - that has to be computed client-side as
    // those inputs change, not fixed at render time.
    $offersByCurrency = $availableCurrencies->mapWithKeys(function ($currency) use ($category, $ratingsByOrgId) {
        $rows = MortgageOffer::query()
            ->where('category', $category)
            ->where('currency', $currency)
            ->whereHas('organization', fn ($query) => $query->active())
            ->with('organization')
            ->get()
            // A lapsed promotion isn't part of today's market, so it never
            // enters the table (same rule as MortgageComparison / the
            // benchmark).
            ->reject(fn ($offer) => $offer->promo_ends_at !== null && $offer->promo_ends_at->isPast())
            ->map(function ($offer) use ($ratingsByOrgId) {
                // Rank on APR when the bank publishes it, else nominal.
                $eff = $offer->apr_min !== null ? (float) $offer->apr_min : (float) $offer->interest_rate_min;

                $badges = [];
                if ($offer->rate_type !== \App\Enums\MortgageRateType::FIXED) {
                    $badges[] = 'floating';
                }
                if ($offer->promo_ends_at !== null) {
                    $badges[] = 'promo';
                }
                if ($offer->apr_min === null) {
                    $badges[] = 'rate_only';
                }
                if ($offer->scraped_at !== null && $offer->scraped_at->lt(now()->subDays(45))) {
                    $badges[] = 'stale';
                }

                return [
                    'id' => $offer->organization->id,
                    'name' => $offer->organization->name,
                    'url' => route('organizations.show', $offer->organization),
                    'logo' => $offer->organization->logo,
                    'initial' => mb_strtoupper(mb_substr($offer->organization->name, 0, 1)),
                    'rate_min' => (float) $offer->interest_rate_min,
                    'rate_max' => (float) $offer->interest_rate_max,
                    'apr_min' => $offer->apr_min !== null ? (float) $offer->apr_min : null,
                    'apr_max' => $offer->apr_max !== null ? (float) $offer->apr_max : null,
                    'eff_rate' => $eff,
                    'basis' => $offer->apr_min !== null ? 'apr' : 'nominal',
                    'badges' => $badges,
                    'term_min_months' => $offer->term_min_months,
                    'term_max_months' => $offer->term_max_months,
                    'min_down_payment_percent' => $offer->min_down_payment_percent !== null ? (float) $offer->min_down_payment_percent : 0,
                    'min_amount' => $offer->min_amount !== null ? (float) $offer->min_amount : 0,
                    'max_amount' => $offer->max_amount !== null ? (float) $offer->max_amount : 999999999999,
                    'source_url' => $offer->source_url,
                    'rating' => (float) ($ratingsByOrgId[$offer->organization_id]->reviews_avg_rating ?? 0),
                    'reviews_count' => (int) ($ratingsByOrgId[$offer->organization_id]->reviews_count ?? 0),
                ];
            })
            ->values();

        return [$currency => $rows];
    })->filter(fn ($rows) => count($rows) > 0);

    $defaultCurrency = $availableCurrencies->first(fn ($currency) => ($offersByCurrency[$currency] ?? collect())->isNotEmpty())
        ?? $availableCurrencies->first();

    $defaultDownPaymentPercent = 20;
    $defaultTermMonths = 60;

    /*
     * The same ranking Alpine does (resources/js/mortgage-table.js), for the
     * values the inputs start on.
     *
     * It is duplicated deliberately. The table used to live entirely in a
     * <template x-for>, so the page painted without it and then grew by
     * ~975px the moment Alpine booted; rendering the first state here is what
     * removes that jump. Alpine then only moves, hides and relabels the rows
     * below - it never creates them. Change one of these and you must change
     * the other, or first paint stops matching first render.
     */
    $monthlyPayment = function (float $ratePercent, float $principal, int $months): float {
        $rate = $ratePercent / 100 / 12;

        if ($months <= 0) {
            return 0.0;
        }

        if ($rate === 0.0) {
            return $principal / $months;
        }

        return $principal * $rate * (1 + $rate) ** $months / ((1 + $rate) ** $months - 1);
    };

    $initialView = $offersByCurrency->mapWithKeys(function ($rows, $currency) use (
        $defaultPropertyPrice, $defaultDownPaymentPercent, $defaultTermMonths, $monthlyPayment
    ) {
        $rows = $rows->all();
        $loanAmount = max(0, ($defaultPropertyPrice[$currency] ?? 0) * (1 - $defaultDownPaymentPercent / 100));

        $bestPerBank = [];

        foreach ($rows as $index => $row) {
            $eligible = $loanAmount >= $row['min_amount']
                && $loanAmount <= $row['max_amount']
                && $defaultDownPaymentPercent >= $row['min_down_payment_percent']
                // Null terms compare exactly as they do in JS: a missing
                // ceiling fails `termMonths <= null` and drops the offer.
                && $defaultTermMonths >= (int) $row['term_min_months']
                && $defaultTermMonths <= (int) $row['term_max_months'];

            if (! $eligible) {
                continue;
            }

            if (! isset($bestPerBank[$row['id']]) || $row['eff_rate'] < $rows[$bestPerBank[$row['id']]]['eff_rate']) {
                $bestPerBank[$row['id']] = $index;
            }
        }

        // JS walks an object with integer-like keys in ascending numeric
        // order, which is what settles rows the sort below leaves tied.
        ksort($bestPerBank, SORT_NUMERIC);

        $payments = [];
        foreach ($bestPerBank as $index) {
            $payments[$index] = $monthlyPayment($rows[$index]['eff_rate'], $loanAmount, $defaultTermMonths);
        }

        $ranked = array_values($bestPerBank);
        usort($ranked, fn ($a, $b) => ($rows[$a]['eff_rate'] <=> $rows[$b]['eff_rate'])
            ?: ($payments[$a] <=> $payments[$b]));

        $positions = [];
        foreach ($ranked as $position => $rowIndex) {
            $positions[$rowIndex] = $position;
        }

        return [$currency => ['positions' => $positions, 'payments' => $payments]];
    });
@endphp

@if ($availableCurrencies->isNotEmpty())
    {{-- The component object lives in resources/js/mortgage-table.js: it is
         far too large to read inside an attribute, and its maths has to be
         readable next to the PHP mirror above. --}}
    <div
        x-data="mortgageTable(@js([
            'currency' => $defaultCurrency,
            'propertyPrice' => $defaultPropertyPrice,
            'downPaymentPercent' => $defaultDownPaymentPercent,
            'termMonths' => $defaultTermMonths,
            'offersByCurrency' => $offersByCurrency,
        ]))"
    >
        <p class="px-6 pt-4 text-xs font-medium tracking-wide text-subtle uppercase">
            {{ __('offers.mortgage_table.category_secondary_market') }}
        </p>

        {{-- Currency tabs --}}
        <div class="flex flex-wrap gap-2 px-6 py-4">
            @foreach ($availableCurrencies as $currency)
                <button
                    type="button"
                    @click="currencyTab = @js($currency)"
                    :class="currencyTab === @js($currency) ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                >
                    {{ $currency }}
                </button>
            @endforeach
        </div>

        {{-- Calculator inputs --}}
        <div class="grid grid-cols-2 gap-4 border-y border-placeholder bg-placeholder/10 px-6 py-4 sm:grid-cols-3">
            <label class="block">
                <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.mortgage_table.property_price') }}</span>
                <input
                    type="number"
                    min="0"
                    x-model.number="propertyPrice[currencyTab]"
                    value="{{ $defaultPropertyPrice[$defaultCurrency] ?? 0 }}"
                    class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm"
                >
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.mortgage_table.down_payment') }} (%)</span>
                <input
                    type="number"
                    min="0"
                    max="100"
                    x-model.number="downPaymentPercent"
                    value="{{ $defaultDownPaymentPercent }}"
                    class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm"
                >
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.mortgage_table.loan_term') }}</span>
                <input
                    type="number"
                    min="1"
                    x-model.number="termMonths"
                    value="{{ $defaultTermMonths }}"
                    class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm"
                >
            </label>
        </div>
        <p class="px-6 py-3 text-sm text-muted">
            {{ __('offers.mortgage_table.loan_amount') }}:
            {{-- Rendered as well as bound: an empty span here is a visibly
                 half-written sentence until Alpine boots. --}}
            <span class="font-semibold text-ink" x-text="format(loanAmount)">{{ number_format(max(0, ($defaultPropertyPrice[$defaultCurrency] ?? 0) * (1 - $defaultDownPaymentPercent / 100)), 0, '.', ',') }}</span>
            <span x-text="currencyTab">{{ $defaultCurrency }}</span>
        </p>

        @foreach ($availableCurrencies as $currency)
            @php
                $rows = ($offersByCurrency[$currency] ?? collect())->all();
                $positions = $initialView[$currency]['positions'] ?? [];
                $payments = $initialView[$currency]['payments'] ?? [];
            @endphp

            <div x-show="currencyTab === @js($currency)" @if ($currency !== $defaultCurrency) x-cloak @endif>
                <p
                    x-show="view.count === 0"
                    @if (count($positions) > 0) x-cloak @endif
                    class="px-6 py-16 text-center text-sm text-muted"
                >{{ __('offers.mortgage_table.no_eligible') }}</p>

                <div x-show="view.count > 0" @if (count($positions) === 0) x-cloak @endif class="border-t border-placeholder">
                    {{-- Column header --}}
                    <div class="flex items-center gap-4 border-b border-placeholder bg-placeholder/20 px-6 py-2 text-xs font-semibold text-subtle uppercase">
                        <span class="w-8 shrink-0"></span>
                        <span class="w-10 shrink-0"></span>
                        <span class="min-w-0 flex-1"></span>
                        <span class="hidden w-24 shrink-0 text-right sm:block">{{ __('offers.mortgage_table.rate') }}</span>
                        <span class="hidden w-24 shrink-0 text-right md:block">{{ __('offers.mortgage_table.down_payment') }}</span>
                        <span class="w-32 shrink-0 text-right">{{ __('offers.mortgage_table.monthly_payment') }}</span>
                        <span class="hidden w-24 shrink-0 text-right sm:block"></span>
                    </div>

                    {{-- flex-col so a row can be placed with CSS `order`; -mb-px
                         hides the last row's bottom border under the panel
                         border, which last:border-b-0 can no longer do now that
                         DOM order is not paint order. --}}
                    <div class="-mb-px flex flex-col">
                        @foreach ($rows as $i => $row)
                            <x-mortgage-offer-row
                                :row="$row"
                                :index="$i"
                                :position="$positions[$i] ?? null"
                                :payment="$payments[$i] ?? null"
                            />
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="px-6 py-16 text-center text-sm text-muted">{{ __('offers.mortgage_table.no_data') }}</p>
@endif
