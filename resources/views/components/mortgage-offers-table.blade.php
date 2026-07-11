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
            ->map(fn ($offer) => [
                'id' => $offer->organization->id,
                'name' => $offer->organization->name,
                'url' => route('organizations.show', $offer->organization),
                'logo' => $offer->organization->logo,
                'initial' => mb_strtoupper(mb_substr($offer->organization->name, 0, 1)),
                'rate_min' => (float) $offer->interest_rate_min,
                'rate_max' => (float) $offer->interest_rate_max,
                'term_min_months' => $offer->term_min_months,
                'term_max_months' => $offer->term_max_months,
                'min_down_payment_percent' => $offer->min_down_payment_percent !== null ? (float) $offer->min_down_payment_percent : 0,
                'min_amount' => $offer->min_amount !== null ? (float) $offer->min_amount : 0,
                'max_amount' => $offer->max_amount !== null ? (float) $offer->max_amount : 999999999999,
                'source_url' => $offer->source_url,
                'rating' => (float) ($ratingsByOrgId[$offer->organization_id]->reviews_avg_rating ?? 0),
                'reviews_count' => (int) ($ratingsByOrgId[$offer->organization_id]->reviews_count ?? 0),
            ])
            ->values();

        return [$currency => $rows];
    })->filter(fn ($rows) => count($rows) > 0);

    $defaultCurrency = $availableCurrencies->first(fn ($currency) => ($offersByCurrency[$currency] ?? collect())->isNotEmpty())
        ?? $availableCurrencies->first();
@endphp

@if ($availableCurrencies->isNotEmpty())
    <div
        x-data="{
            currencyTab: '{{ $defaultCurrency }}',
            propertyPrice: @js($defaultPropertyPrice),
            downPaymentPercent: 20,
            termMonths: 60,
            offersByCurrency: @js($offersByCurrency),

            get loanAmount() {
                return Math.max(0, (this.propertyPrice[this.currencyTab] || 0) * (1 - this.downPaymentPercent / 100));
            },

            monthlyPayment(ratePercent, principal, months) {
                const r = ratePercent / 100 / 12;
                if (months <= 0) return 0;
                if (r === 0) return principal / months;
                return principal * r * Math.pow(1 + r, months) / (Math.pow(1 + r, months) - 1);
            },

            get ranked() {
                const rows = this.offersByCurrency[this.currencyTab] || [];
                const bestPerBank = {};

                rows.forEach((row) => {
                    const eligible = this.loanAmount >= row.min_amount
                        && this.loanAmount <= row.max_amount
                        && this.downPaymentPercent >= row.min_down_payment_percent
                        && this.termMonths >= row.term_min_months
                        && this.termMonths <= row.term_max_months;

                    if (!eligible) return;

                    if (!bestPerBank[row.id] || row.rate_min < bestPerBank[row.id].rate_min) {
                        bestPerBank[row.id] = row;
                    }
                });

                return Object.values(bestPerBank)
                    .map((row) => ({ ...row, payment: this.monthlyPayment(row.rate_min, this.loanAmount, this.termMonths) }))
                    .sort((a, b) => a.payment - b.payment);
            },
        }"
    >
        <p class="px-6 pt-4 text-xs font-medium tracking-wide text-subtle uppercase">
            {{ __('offers.mortgage_table.category_secondary_market') }}
        </p>

        {{-- Currency tabs --}}
        <div class="flex flex-wrap gap-2 px-6 py-4">
            @foreach ($availableCurrencies as $currency)
                <button
                    type="button"
                    @click="currencyTab = '{{ $currency }}'"
                    :class="currencyTab === '{{ $currency }}' ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
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
                    class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm"
                >
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.mortgage_table.loan_term') }}</span>
                <input
                    type="number"
                    min="1"
                    x-model.number="termMonths"
                    class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm"
                >
            </label>
        </div>
        <p class="px-6 py-3 text-sm text-muted">
            {{ __('offers.mortgage_table.loan_amount') }}:
            <span class="font-semibold text-ink" x-text="loanAmount.toLocaleString()"></span>
            <span x-text="currencyTab"></span>
        </p>

        @foreach ($availableCurrencies as $currency)
            <div x-show="currencyTab === '{{ $currency }}'" @if ($currency !== $defaultCurrency) x-cloak @endif>
                <template x-if="ranked.length === 0">
                    <p class="px-6 py-16 text-center text-sm text-muted">{{ __('offers.mortgage_table.no_eligible') }}</p>
                </template>

                <template x-if="ranked.length > 0">
                    <div class="border-t border-placeholder">
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

                        <template x-for="(row, index) in ranked" :key="row.id">
                            <div class="flex items-center gap-4 border-b border-placeholder px-6 py-5 last:border-b-0">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                    :class="index === 0 ? 'bg-accent-yellow text-ink' : 'bg-placeholder/60 text-muted'"
                                    x-text="index + 1"
                                ></span>

                                <img x-show="row.logo" :src="row.logo" :alt="row.name" class="h-10 w-10 shrink-0 rounded-full object-contain">
                                <div
                                    x-show="!row.logo"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                    x-text="row.initial"
                                ></div>

                                <div class="min-w-0 flex-1">
                                    <a :href="row.url" class="block truncate text-sm font-medium text-ink hover:text-primary" x-text="row.name"></a>
                                    <div x-show="row.reviews_count > 0" class="mt-0.5 flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-3 w-3 fill-accent-yellow">
                                            <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                                        </svg>
                                        <span class="text-xs text-subtle" x-text="row.rating.toFixed(1) + ' (' + row.reviews_count + ')'"></span>
                                    </div>
                                </div>

                                <div class="hidden w-24 shrink-0 text-right sm:block">
                                    <p class="text-sm font-semibold text-ink">
                                        <span x-text="row.rate_min === row.rate_max ? row.rate_min : row.rate_min + '-' + row.rate_max"></span>%
                                    </p>
                                </div>

                                <div class="hidden w-24 shrink-0 text-right md:block">
                                    <p class="text-sm text-ink" x-text="row.min_down_payment_percent + '%+'"></p>
                                </div>

                                <div class="w-32 shrink-0 text-right">
                                    <p class="font-heading text-lg font-bold text-primary" x-text="Math.round(row.payment).toLocaleString()"></p>
                                    <p class="text-xs text-subtle">/ {{ __('offers.per_month') }}</p>
                                </div>

                                <div class="hidden w-24 shrink-0 text-right sm:block">
                                    <a
                                        x-show="row.source_url"
                                        :href="row.source_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-xs font-medium text-primary hover:underline"
                                    >
                                        {{ __('offers.mortgage_table.view_details') }}
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        @endforeach
    </div>
@else
    <p class="px-6 py-16 text-center text-sm text-muted">{{ __('offers.mortgage_table.no_data') }}</p>
@endif
