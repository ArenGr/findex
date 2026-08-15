@extends('layouts.app')

@section('title', __('rates.all_heading') . ' — Findex')

@php
    use Illuminate\Support\Carbon;

    // Carries the current state onto every link/form on this page so changing
    // one control never silently resets the others.
    $baseParams = [
        'type' => $selectedType->value,
        'org_type' => $selectedOrgType,
        'organization' => $selectedOrganization?->slug,
        'city' => $selectedCity,
        'sort' => $sort,
        'dir' => $direction,
        'lat' => $latitude,
        'lng' => $longitude,
        'intent' => $intent,
        'amount' => $amount,
        'open' => $openNow ? 1 : null,
        'view' => $viewMode === 'map' ? 'map' : null,
        'q' => $search !== '' ? $search : null,
    ];

    $link = fn (array $overrides = []) => route('rates.index', array_filter(
        [...$baseParams, 'currency' => $selectedCurrency?->code, ...$overrides],
        fn ($value) => $value !== null && $value !== '',
    ));

    $hasNonDefaultFilter = $selectedType !== \App\Enums\RateType::CASH
        || $selectedOrgType || $selectedOrganization || $selectedCity || $hasLocation
        || $amount !== null || $openNow || $search !== '';


    // Whole dram above 1,000, where a rounded half-unit is noise. Below it the
    // decimals are the number: 1 KZT at 4.60 rendered as "5", and the office
    // beside it quoting 4.75 rendered as "5" too, so the table stopped telling
    // two rows apart at exactly the amounts where the gap is easiest to see.
    $amd = fn (float $value) => $value < 1000
        ? number_format($value, 2)
        : number_format($value);

    // One table either way. Without an amount it answers "what are today's
    // rates"; with one it answers "what do I get", by gaining a column rather
    // than by becoming a different table.
    $calculating = $amount !== null;

    // Which column the visitor's intent ranks on, and therefore which one the
    // total is derived from.
    $rateField = \App\Http\Controllers\RateController::rateFieldForIntent($intent);
    $isBuying = $intent === 'buy';

    // "best" is not a column - it is the ordering the page arrives in, which
    // runs on whichever rate column the visitor's intent points at. Naming that
    // column here means the heading carries the arrow, rather than the table
    // being sorted by something with no mark on it.
    $activeSortColumn = $sort === 'best' ? ($isBuying ? 'sell' : 'buy') : $sort;

    // The direction each column is usually asked in: the highest buy rate, the
    // lowest sell rate, the tightest spread, the newest update. A first press
    // gives that; pressing the same heading again reverses it.
    $naturalSort = ['buy' => 'desc', 'sell' => 'asc', 'spread' => 'asc', 'updated' => 'desc'];

    $sortHref = fn (string $column) => $link([
        'sort' => $column,
        'dir' => $activeSortColumn === $column
            ? ($direction === 'asc' ? 'desc' : 'asc')
            : $naturalSort[$column],
    ]);

    // "I have X, I want Y" instead of Buy/Sell. Buy and sell are written from
    // the institution's side - the column headed "Sell" is the one a buyer pays
    // - and asking a visitor to decode that is the oldest trap on this page.
    // The backend still speaks buy/sell; only the wording here changed.
    //
    // The amount is denominated in whatever the visitor HAS, which means the
    // arithmetic differs by direction rather than only the column it reads:
    // handing over foreign currency multiplies by the organization's buy rate,
    // and handing over dram divides by its sell rate.
    $amdCode = __('exchange_quotes.request.amd');
    $currencyCode = $selectedCurrency?->code;
    $sourceCode = $isBuying ? $amdCode : $currencyCode;
    $targetCode = $isBuying ? $currencyCode : $amdCode;

    $convert = fn (float $rate) => $isBuying ? $amount / $rate : $amount * $rate;

    // A rate this old is worth flagging - banks republish through the day, so
    // anything past a day is no longer "today's rate".
    $staleAfterHours = 24;
    $isStale = fn ($scrapedAt) => $scrapedAt && Carbon::parse($scrapedAt)->diffInHours(now()) >= $staleAfterHours;

    $rowCount = $ranked['count'];
    $allStale = $rowCount > 0 && collect($ranked['rows'])->every(fn ($row) => $isStale($row->scraped_at));

    // Shown once above the table rather than per market, now that everything is
    // ranked as one list. Computed from the two converted totals rather than
    // from the rate spread: when the visitor is handing over dram the total is a
    // division, so amount x spread would be the wrong number entirely.
    $marketSaving = $calculating && $ranked['best_value'] !== null && $ranked['worst_value'] !== null
        ? abs($convert((float) $ranked['best_value']) - $convert((float) $ranked['worst_value']))
        : null;

    // Rank is computed independently of the chosen sort, so the winner is the
    // same row whichever column the visitor ordered by. Resolved up here rather
    // than beside the table, because the alert trigger above it prefills a
    // threshold from the going rate.
    $bestRows = collect($ranked['rows'])->where('rank', 1);
    $best = $bestRows->first();

    // Banks and exchange offices share one table, so each row says which it is
    // - but only when the list actually mixes them.
    $showMarket = collect($ranked['rows'])->pluck('organization_type')->unique()->count() > 1;


    $labelClass = 'block text-xs font-semibold tracking-wider text-muted uppercase';

    // What the modal opens with. Hoisted because two things trigger it - the
    // chip above the table and the card below it - and a visitor who used one
    // and then the other should not get a different form.
    $alertPrefill = [
        'form' => [
            'currency_id' => (string) ($selectedCurrency?->id ?? ''),
            'organization_id' => (string) ($selectedOrganization?->id ?? ''),
            'rate_type' => $selectedType->value,
            'rate_field' => $rateField,
            'direction' => $isBuying ? 'below' : 'above',
            'threshold' => $best ? number_format((float) $best->{$rateField}, 2, '.', '') : '',
        ],
        'context' => [
            'currency' => __('exchange_quotes.request.amd'),
            'rate' => $best ? number_format((float) $best->{$rateField}, 2) : null,
        ],
    ];

    $alertHref = route('alerts.index', array_filter(['currency_id' => $selectedCurrency?->id])).'#create-alert';

    // Everything that has moved off its default, so the button can say how
    // much is hidden behind it. The transaction type counts too - "Card" is a
    // narrower view than "Cash" and the visitor should be told they set it.
    $activeFilterCount = collect([
        $selectedType !== \App\Enums\RateType::CASH ? $selectedType : null,
        $selectedOrgType, $selectedOrganization, $selectedCity, $hasLocation ?: null, $openNow ?: null,
    ])->filter()->count();

@endphp

@section('content')
    <section id="rates-panel" class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        {{--
            Heading on the left, the page's two offers on the right, baselines
            aligned. They sat here before as plain text at the same ink, size and
            weight as the paragraph beside them, which nobody read as something
            you could press - so they are buttons now, one filled and one
            outlined. Two solid greens side by side would make the visitor choose
            between them, when negotiating is the offer no competitor can match
            and watching a rate is the fallback for everyone else.

            Stacked below md: at 390px the heading and two buttons cannot share
            a row without the buttons shrinking below their own labels.
        --}}
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div class="min-w-0">
                <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink">{{ __('rates.all_heading') }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('rates.all_subheading') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 md:shrink-0">
                @if ($quoteMinimum !== null)
                    @php $qualifies = $amount >= $quoteMinimum; @endphp
                    <a
                        {{-- Everything this page already knows goes with them:
                        asking for the currency, the amount, the city and the
                        direction a second time is the surest way to lose
                        someone between the two pages. The amount travels even
                        when it is below the minimum - the form states its own
                        minimum, and blanking the number they typed teaches them
                        nothing. --}}
                        @php
                            // The exchange form denominates the amount in the
                            // foreign currency whichever way the trade runs -
                            // its minimum is "1,000 USD" regardless. This page
                            // denominates it in whatever the visitor HAS, so a
                            // dram amount has to be converted before it crosses
                            // over, or "100,000 AMD" arrives as "100,000 USD"
                            // and the form asks them to confirm a transaction
                            // 370 times the size of the one they wanted.
                            $handoverAmount = $amount === null
                                ? null
                                : ($isBuying && $best
                                    ? round($convert((float) $best->{$rateField}), 2)
                                    : $amount);
                        @endphp
                        {{-- A real href, so it still works with JS off and can
                        be opened in a new tab. The click is intercepted to open
                        the modal instead, already holding this page's state -
                        the question is about the rates on screen, so it belongs
                        on top of them rather than on another page. --}}
                        href="{{ route('exchange.request', array_filter([
                            'currency' => $selectedCurrency?->code,
                            'amount' => $handoverAmount,
                            'city' => $selectedCity,
                            'rate_field' => $rateField,
                        ])) }}"
                        onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('better-rate-open', { detail: {{ Js::from([
                            'form' => [
                                'currency_code' => (string) ($selectedCurrency?->code ?? ''),
                                'amount' => $handoverAmount === null ? '' : (string) $handoverAmount,
                                'rate_field' => $rateField,
                                'preferred_city' => (string) ($selectedCity ?? ''),
                            ],
                            'context' => [
                                'code' => (string) ($selectedCurrency?->code ?? ''),
                                'rate' => $best ? number_format((float) $best->{$rateField}, 2) : null,
                                'total' => $best && $handoverAmount ? $amd($handoverAmount * (float) $best->{$rateField}) : null,
                            ],
                        ]) }} }))"
                        class="inline-flex min-w-0 items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-dark"
                    >
                        {{-- Two speech bubbles. Not exchange arrows, which read
                        as "swap currency" - what the whole page already does -
                        and not a handshake, whose five overlapping strokes
                        collapse into a blob at this size. Not a percent badge
                        either: that promises a discount, and all we can promise
                        is that the question gets asked. --}}
                        <svg
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                            stroke-linecap="round" stroke-linejoin="round"
                            class="h-5 w-5 shrink-0" aria-hidden="true"
                        >
                            <path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z" />
                            <path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1" />
                        </svg>
                        <span class="min-w-0 break-words">{{ __('rates.cta_button') }}</span>
                    </a>

                    {{-- Who this is for, kept out of the button: it only matters
                    to the people who stop to ask. --}}
                    <x-info-popover :label="__('rates.cta_button')">
                        <p class="font-semibold text-ink">
                            @if ($qualifies)
                                {{ __('rates.cta_heading_qualified', ['amount' => number_format($amount), 'code' => $selectedCurrency?->code]) }}
                            @else
                                {{ __('rates.cta_heading', ['amount' => number_format($quoteMinimum), 'code' => $selectedCurrency?->code]) }}
                            @endif
                        </p>
                        <p class="mt-2">{{ __('rates.cta_body') }}</p>
                        <p class="mt-2 text-xs">{{ __('rates.cta_note') }}</p>
                    </x-info-popover>
                @endif

                <a
                    href="{{ $alertHref }}"
                    onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('rate-alert-open', { detail: {{ Js::from($alertPrefill) }} }))"
                    class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-placeholder bg-white px-4 py-2 text-sm font-medium text-ink transition hover:bg-placeholder/25"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-accent-yellow" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" clip-rule="evenodd" />
                    </svg>
                    <span class="min-w-0 break-words">{{ __('rates.alert_cta') }}</span>
                </a>

                <x-info-popover :label="__('rates.alert_cta')">
                    {{ __('rates.alert_hint') }}
                </x-info-popover>
            </div>
        </div>

        {{--
            Currency is the only question here with no sensible default, so it
            is the only one asked on sight. Everything else - market, kind of
            transaction, city, and the calculator - has an answer already and
            waits behind a control, because a page called "All Exchange Rates"
            that shows no rates until you scroll is answering the wrong
            question first.

            Currency is a set of links rather than a field: picking one
            navigates, carrying the current amount and intent.
        --}}
        @php
            // Four chips answer the question for almost everyone; the other
            // seven turn one decision into eleven and push the rates
            // themselves off the screen. They are one click away, not gone.
            // config/rates.php is newer than some deployed config caches, and
            // a cache built before it existed returns null here - which took
            // the whole page down on a missing file rather than on a missing
            // rate. Falling back to every currency degrades to the behaviour
            // this control replaced; falling back to [] would have hidden all
            // eleven behind the button, which is worse than showing them.
            $everyday = config('rates.everyday');
            $everyday = is_array($everyday) && $everyday !== []
                ? $everyday
                : $currencies->pluck('code')->all();
            $currencyChip = fn ($currency) => $selectedCurrency?->id === $currency->id
                ? 'border-primary/50 bg-primary/20 text-ink'
                : 'border-placeholder bg-white text-muted hover:text-ink';
            [$commonCurrencies, $otherCurrencies] = $currencies->partition(
                fn ($currency) => in_array($currency->code, $everyday, true)
            );
            // A currency picked from behind the button must not disappear when
            // the page reloads on it - that reads as the choice being lost.
            $othersOpen = $otherCurrencies->contains(fn ($currency) => $selectedCurrency?->id === $currency->id);
        @endphp

        <div class="mt-8" x-data="{ showAll: @js($othersOpen) }">
            <span class="{{ $labelClass }}">{{ __('rates.currency_label') }}</span>
            {{-- On a phone the row scrolls sideways rather than wrapping. --}}
            <div class="mt-2 flex gap-2 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:overflow-visible [&::-webkit-scrollbar]:hidden">
                @foreach ($commonCurrencies as $currency)
                    <a
                        href="{{ $link(['currency' => $currency->code]) }}"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold tracking-wide uppercase transition {{ $currencyChip($currency) }}"
                    >
                        <span aria-hidden="true" class="text-base">{{ \App\Models\Currency::flag($currency->code) }}</span>
                        {{ $currency->code }}
                    </a>
                @endforeach

                @if ($otherCurrencies->isNotEmpty())
                    {{-- The rest sit in the same row, so opening them extends
                    the line of chips rather than adding a second control. --}}
                    @foreach ($otherCurrencies as $currency)
                        <a
                            href="{{ $link(['currency' => $currency->code]) }}"
                            x-show="showAll"
                            x-cloak
                            class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold tracking-wide uppercase transition {{ $currencyChip($currency) }}"
                        >
                            <span aria-hidden="true" class="text-base">{{ \App\Models\Currency::flag($currency->code) }}</span>
                            {{ $currency->code }}
                        </a>
                    @endforeach

                    <button
                        type="button"
                        @click="showAll = !showAll"
                        :aria-expanded="showAll ? 'true' : 'false'"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-dashed border-border-muted bg-white px-4 py-2 text-sm font-medium text-muted transition hover:border-primary/50 hover:text-ink"
                    >
                        {{-- The count says what is behind the button, so it is
                        not a guess whether pressing it is worth it. --}}
                        <span x-show="!showAll">+{{ $otherCurrencies->count() }}</span>
                        <span x-text="showAll ? @js(__('rates.currency_fewer')) : @js(__('rates.currency_more'))"></span>
                    </button>
                @endif
            </div>

        </div>


        {{--
            The state in words, the controls behind a button. Hiding filters
            usually breaks because a narrowed table then looks like the full
            one; saying "Cash rates · banks · Gyumri" out loud fixes that
            without keeping thirteen pills on screen.

            Alpine only decides whether the panel is open. Every control inside
            is still a link or a GET form, so with JS off the panel renders
            open and the page works exactly as before.
        --}}
        <div x-data="{ open: window.__ratesFiltersOpen ?? false }" x-effect="window.__ratesFiltersOpen = open" class="mt-6">
            {{-- Narrowing by name and narrowing by market are the same kind of
            act, so they sit together rather than one here and one down beside
            the table where it read as a table setting. --}}
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    @click="open = !open"
                    :aria-expanded="open"
                    class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition {{ $activeFilterCount ? 'border-primary/50 bg-primary/10 text-ink' : 'border-placeholder bg-white text-muted hover:border-border-muted hover:text-ink' }}"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                        <path d="M3 6h18M7 12h10M11 18h2" />
                    </svg>
                    {{ __('rates.more_filters') }}@if ($activeFilterCount) ({{ $activeFilterCount }})@endif
                </button>

                {{-- Finding one organization in the table is a different job
                from ranking all of them, and it was the one the page had no
                answer for: you read fourteen names looking for yours.

                A GET form, so the result is a URL like every other state on
                this page - shareable, bookmarkable, and working with JS off.
                Only offered over a list; there is nothing to narrow on a map,
                which shows every branch by position. --}}
                @if ($viewMode !== 'map')
                    <form method="GET" action="{{ route('rates.index') }}" class="flex w-full min-w-0 items-center gap-2 sm:w-auto sm:max-w-sm sm:flex-1">
                        @foreach (['currency', 'type', 'org_type', 'organization', 'city', 'lat', 'lng', 'intent', 'amount', 'sort', 'dir', 'open'] as $carried)
                            @php $value = $carried === 'currency' ? $selectedCurrency?->code : ($baseParams[$carried] ?? null); @endphp
                            @if (! empty($value))
                                <input type="hidden" name="{{ $carried }}" value="{{ $value }}">
                            @endif
                        @endforeach

                        <label for="q" class="sr-only">{{ __('rates.search_label') }}</label>
                        <div class="relative min-w-0 flex-1">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute top-1/2 start-3 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                            </svg>
                            {{-- Searches itself once there is enough typed to
                            mean something. Three characters, because one or two
                            match most of the list and firing on every keystroke
                            would ask the server about "b" on the way to "bank".
                            Emptying it searches again, which is how you get the
                            whole table back without reaching for a button.

                            The button below still exists for Enter and for no
                            JS at all; this only saves the press. --}}
                            <input
                                type="search" name="q" id="q"
                                value="{{ $search }}"
                                placeholder="{{ __('rates.search_placeholder') }}"
                                autocomplete="off"
                                @input.debounce.350ms="if ($el.value.trim().length >= 3 || $el.value.trim() === '') $el.form.requestSubmit()"
                                @search="$el.form.requestSubmit()"
                                class="min-h-11 w-full min-w-0 rounded-xl border border-placeholder bg-white py-2 ps-9 pe-3 text-sm text-ink focus:border-primary focus:outline-none"
                            >
                        </div>

                        {{-- The submit is what makes it work without JS. With
                        JS the field self-submits on the search input's own
                        clear button too, so "x" empties the table filter
                        rather than just the box. --}}
                        <button type="submit" class="sr-only focus:not-sr-only focus:rounded-lg focus:border focus:border-primary focus:px-3 focus:py-2 focus:text-xs">
                            {{ __('rates.search_label') }}
                        </button>
                    </form>

                    @if ($search !== '')
                        <a href="{{ $link(['q' => null]) }}" class="-my-2 inline-block py-2 text-xs text-muted underline hover:text-ink">
                            {{ __('rates.search_clear') }}
                        </a>
                    @endif
                @endif

            </div>

        {{-- Mobile only. A panel that pushes the table off screen is worse than
        no panel: you cannot see what your choices did. --}}
        <div x-show="open" x-cloak x-transition.opacity @click="open = false" class="fixed inset-0 z-40 bg-ink/40 sm:hidden"></div>

        {{--
            A bottom sheet below sm, the same inline panel from sm up. The
            controls inside are identical either way - only the box they sit in
            changes, so there is one panel to maintain rather than two.
        --}}
        <div
            x-show="open" x-cloak
            role="group" aria-label="{{ __('rates.more_filters') }}"
            class="fixed inset-x-0 bottom-0 z-50 flex max-h-[85vh] flex-col items-start gap-y-5 overflow-y-auto rounded-t-2xl border-t border-placeholder bg-white px-5 pt-5 pb-5 sm:static sm:z-auto sm:mt-5 sm:max-h-none sm:flex-row sm:flex-wrap sm:gap-x-10 sm:overflow-visible sm:rounded-xl sm:border"
        >
            {{-- The sheet needs a title and a way out; the inline panel has the
            button it opened from sitting right above it. --}}
            <div class="flex w-full items-center justify-between sm:hidden">
                <span class="font-heading text-base font-bold text-ink">{{ __('rates.more_filters') }}</span>
                <button type="button" @click="open = false" class="-mr-1 rounded-full p-2 text-muted hover:bg-placeholder/30 hover:text-ink" aria-label="{{ __('alerts.modal.cancel') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

        {{--
            One control per question, each stating its own answer while shut -
            "Market / Banks" rather than a row of pills where the chosen one is
            told apart only by its tint. Thirteen pills became four controls
            that fit on one line.

            Every option inside is a real link, so the panel is shareable and
            works with JavaScript off - see the component for how.
        --}}
        {{-- min-w-0 so this can shrink, and only then wrap: a flex item at its
        default min-width: auto refuses to go below its content, so four
        triggers that did not fit ran off the side of a tablet instead of
        moving onto a second line. --}}
        <div class="flex w-full min-w-0 flex-col gap-3 sm:w-auto sm:flex-1 sm:flex-row sm:flex-wrap sm:items-start">
            {{-- Banks and exchange offices quote very different levels, so they
            are separated rather than interleaved. --}}
            @if ($orgTypes->count() > 1)
                <x-rates.filter-menu
                    :label="__('rates.market_label')"
                    :active="$selectedOrgType !== null"
                    :options="[
                        [
                            'label' => __('rates.market_all'),
                            'href' => $link(['org_type' => null, 'organization' => null]),
                            'selected' => $selectedOrgType === null,
                        ],
                        ...$orgTypes->map(fn ($orgType) => [
                            'label' => __('rates.markets.' . $orgType),
                            'href' => $link(['org_type' => $orgType, 'organization' => null]),
                            'selected' => $selectedOrgType === $orgType,
                        ])->all(),
                    ]"
                />
            @endif

            {{-- Only the types this currency actually has, so an option never
            leads to an empty table. --}}
            <x-rates.filter-menu
                :label="__('rates.type_label')"
                :active="$selectedType !== \App\Enums\RateType::CASH"
                :options="collect($availableTypes)->map(fn ($typeValue) => [
                    'label' => __('organizations.rate_types.' . $typeValue),
                    'href' => $link(['type' => $typeValue]),
                    'selected' => $selectedType->value === $typeValue,
                ])->all()"
            />

            {{-- Only once a market is chosen. Under "All" the list mixes banks
            and exchange offices, and no single label is honest about what it
            contains. --}}
            @if ($selectedOrgType !== null && $organizations->isNotEmpty())
                <x-rates.filter-menu
                    :label="__('rates.filter_org.'.$selectedOrgType)"
                    :active="$selectedOrganization !== null"
                    :options="[
                        [
                            'label' => __('rates.filter_org_all.'.$selectedOrgType),
                            'href' => $link(['organization' => null]),
                            'selected' => $selectedOrganization === null,
                        ],
                        ...$organizations->map(fn ($organization) => [
                            'label' => $organization->name,
                            'href' => $link(['organization' => $organization->slug]),
                            'selected' => $selectedOrganization?->id === $organization->id,
                        ])->all(),
                    ]"
                />
            @endif

            @if ($cities->isNotEmpty())
                <x-rates.filter-menu
                    :label="__('rates.filter_city')"
                    :hint="__('rates.filter_city_hint')"
                    searchable
                    :active="$selectedCity !== null"
                    :options="[
                        [
                            'label' => __('rates.filter_city_all'),
                            'href' => $link(['city' => null]),
                            'selected' => $selectedCity === null,
                        ],
                        ...$cities->map(fn ($city) => [
                            'label' => $city,
                            'href' => $link(['city' => $city]),
                            'selected' => $selectedCity === $city,
                        ])->all(),
                    ]"
                />
            @endif
        </div>

        {{-- Two switches rather than menus: they are on or off, and a menu
        holding "Any time / Open now" would be a longer way of saying that.
        "Open now" excludes branches with no hours on file rather than assuming
        them open - it is a promise that someone is behind a counter. --}}
        <div class="flex w-full flex-wrap items-center gap-2 sm:ms-auto sm:w-auto">
            <a
                href="{{ $link(['open' => $openNow ? null : 1]) }}"
                aria-pressed="{{ $openNow ? 'true' : 'false' }}"
                class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border px-3.5 py-2 text-sm font-semibold transition {{ $openNow ? 'border-primary/50 bg-primary/10 text-ink' : 'border-placeholder bg-white text-muted hover:border-border-muted hover:text-ink' }}"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
                </svg>
                {{ __('rates.open_now') }}
            </a>

            {{--
                Client-side only - the server can't know the visitor's
                coordinates until the browser's own prompt hands them over.
                Once granted, reloads the current URL with lat/lng merged in,
                keeping every other filter intact.
            --}}
            <div x-data="{
                    state: 'idle',
                    findNearby() {
                        if (!navigator.geolocation) { this.state = 'error'; return; }
                        this.state = 'locating';
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                const url = new URL(window.location.href);
                                url.searchParams.set('lat', position.coords.latitude);
                                url.searchParams.set('lng', position.coords.longitude);
                                url.searchParams.set('sort', 'distance');
                                url.searchParams.set('direction', 'asc');
                                window.dispatchEvent(new CustomEvent('rates:navigate', { detail: url.toString() }));
                            },
                            () => { this.state = 'error'; },
                        );
                    },
                }">
                    @if ($hasLocation)
                        <a
                            href="{{ $link(['lat' => null, 'lng' => null, 'sort' => null, 'dir' => null]) }}"
                            class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-primary/50 bg-primary/10 px-3.5 py-2 text-sm font-semibold text-ink"
                        >
                            📍 {{ __('rates.nearby_active') }}
                            <span aria-hidden="true" class="text-muted">&times;</span>
                        </a>
                    @else
                        <button
                            type="button"
                            @click="findNearby()"
                            :disabled="state === 'locating'"
                            class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-placeholder bg-white px-3.5 py-2 text-sm font-semibold text-muted transition hover:border-border-muted hover:text-ink disabled:opacity-60"
                        >
                            <span x-show="state !== 'locating'">📍 {{ __('rates.find_nearby') }}</span>
                            <span x-show="state === 'locating'" x-cloak>{{ __('rates.locating') }}</span>
                        </button>
                        <p x-show="state === 'error'" x-cloak class="mt-1 text-xs text-red-600">{{ __('rates.location_error') }}</p>
                    @endif
            </div>
        </div>

            {{-- Every control in here applies on click, so this confirms
            rather than submits - it says what the choices left you with and
            gets the sheet out of the way. Sticky, because the panel scrolls. --}}
            <div class="sticky bottom-0 -mx-5 mt-1 w-[calc(100%+2.5rem)] border-t border-placeholder bg-white px-5 pt-4 sm:hidden">
                <button type="button" @click="open = false" class="w-full rounded-lg bg-primary px-6 py-3 text-sm font-semibold break-words text-white transition hover:bg-primary-dark">
                    {{ trans_choice('rates.apply_filters', $rowCount, ['count' => $rowCount]) }}
                </button>
            </div>
        </div>
        </div>

        @if ($centralBankRate)
            {{-- Stated once, as a reference. It used to sit in the transaction
            type row as a peer of Cash and Card, which sent visitors to rows
            they could not act on. --}}
            {{-- The caveat rides with the number rather than being left for
            the visitor to know already: an official rate printed beside a table
            of worse ones invites "why is nobody giving me 365?", and the answer
            is that nobody ever could. --}}
            {{--
                A div, not a p: x-info-popover's root is a div, and a div inside
                a p makes the parser close the paragraph early and hoist the
                popover out of it - which is why the "?" kept landing on a line
                of its own no matter how it was styled. The markup looked right
                in the response and wrong in the DOM.
            --}}
            <div class="mt-5 text-sm break-words text-muted">
                {{ __('rates.central_bank_reference', [
                    'rate' => number_format((float) $centralBankRate['rate'], 2),
                    'code' => $selectedCurrency?->code,
                ]) }}
            </div>
        @endif

        @php
            // Without an amount there is no single winner - buy and sell rank
            // in opposite directions - so each column names its own. The
            // organization buys low and sells high, which for the visitor is
            // the highest buy and the lowest sell.
            $bestBuy = collect($ranked['rows'])->max(fn ($row) => (float) $row->buy_rate);
            $bestSell = collect($ranked['rows'])->min(fn ($row) => (float) $row->sell_rate);
            $isBestRate = fn (float $value, ?float $target) => $target !== null && abs($value - $target) < 0.00005;

            // How many organizations hold each winning figure. Six identical
            // stars with nothing explaining them read as a fault; the chip says
            // "available at 6 organizations" instead.
            $bestBuyCount = collect($ranked['rows'])->filter(fn ($row) => $isBestRate((float) $row->buy_rate, $bestBuy))->count();
            $bestSellCount = collect($ranked['rows'])->filter(fn ($row) => $isBestRate((float) $row->sell_rate, $bestSell))->count();
            $bestTotalCount = $bestRows->count();

            $totalColumn = __('rates.you_receive_column');

            // Who is quoting each winning figure. Ties are the norm - six banks
            // at 367.00 - so the card names the first and counts the rest,
            // exactly as the best-rate band does.
            $holderOf = function (callable $wins) use ($ranked) {
                $rows = collect($ranked['rows'])->filter($wins);

                if ($rows->count() > 1) {
                    return trans_choice('rates.best_shared', $rows->count() - 1, [
                        'name' => $rows->first()->organization_name,
                        'count' => $rows->count() - 1,
                    ]);
                }

                return $rows->first()?->organization_name;
            };

            // Averaged over the column the visitor is actually transacting on,
            // not over the midpoint between buy and sell. The midpoint is a
            // number nobody quotes, and worse, comparing a real total against
            // it produced a saving figure that did not reconcile with the card
            // printed 200px above it - on a page about money, arithmetic that
            // does not add up is a trust problem, not a rounding one.
            // Rounded to the precision it is printed at, so the gain below
            // reconciles exactly with the card: a reader who multiplies the
            // displayed average themselves must arrive at our number, not one
            // 21 dram away from it.
            $marketAverage = round(collect($ranked['rows'])->avg(fn ($row) => (float) $row->{$rateField}), 2);

            // What the winner is worth against that average. The saving beside
            // the results count measures against the WORST rate on the page,
            // which flatters us - nobody would have picked it. This is the
            // honest version of the same claim.
            $averageGain = $calculating && $marketAverage
                ? $convert((float) $ranked['best_value']) - $convert((float) $marketAverage)
                : null;

            $organizationCount = collect($ranked['rows'])->pluck('organization_id')->unique()->count();

            // Green buy, red sell - the pairing every currency board in the
            // country uses, so it is the one visitors arrive already able to
            // read. The average stays ink: it is a reference, not an offer.
            // One rate can put several pins on the map: the rate belongs to the
            // organization, the address to the branch. Assembled here rather
            // than in the controller because the total needs $convert, which is
            // presentation arithmetic and lives with the rest of it.
            $mapPoints = [];

            if ($viewMode === 'map') {
                foreach ($ranked['rows'] as $row) {
                    foreach ($mapBranches[$row->organization_id] ?? [] as $branch) {
                        $mapPoints[] = [
                            'lat' => $branch['lat'],
                            'lng' => $branch['lng'],
                            'name' => $row->organization_name,
                            'branch' => $branch['name'],
                            'address' => $branch['address'],
                            'rate' => number_format((float) $row->{$rateField}, 2),
                            'best' => $row->rank === 1,
                            'total' => $calculating ? $amd($convert((float) $row->{$rateField})).' '.$targetCode : null,
                            'distance' => $hasLocation && isset($row->distance_km)
                                ? __('rates.distance_km', ['km' => number_format($row->distance_km, 1)])
                                : null,
                            'open' => $branch['open'],
                            'openLabel' => $branch['open'] === null
                                ? __('rates.hours_unknown')
                                : ($branch['open'] ? __('rates.open') : __('rates.closed')),
                            'hours' => $branch['hours'],
                            'directions' => 'https://www.google.com/maps/dir/?api=1&destination='.$branch['lat'].','.$branch['lng'],
                            // Same rule as everywhere else: only exchange
                            // offices negotiate, and only for a currency we
                            // broker quotes in.
                            'negotiate' => $row->organization_type === 'exchange' && $quoteMinimum !== null
                                ? route('exchange.request', array_filter([
                                    'currency' => $selectedCurrency?->code,
                                    'amount' => $amount,
                                    'city' => $branch['city'],
                                    'rate_field' => $rateField,
                                ]))
                                : null,
                        ];
                    }
                }
            }

            $summaryCards = [
                [
                    'label' => __('rates.summary_best_buy'),
                    'value' => $bestBuy,
                    'note' => $holderOf(fn ($row) => $isBestRate((float) $row->buy_rate, $bestBuy)),
                    'hint' => __('rates.buy_hint'),
                    'tone' => 'text-primary',
                ],
                [
                    'label' => __('rates.summary_best_sell'),
                    'value' => $bestSell,
                    'note' => $holderOf(fn ($row) => $isBestRate((float) $row->sell_rate, $bestSell)),
                    'hint' => __('rates.sell_hint'),
                    'tone' => 'text-accent-red',
                ],
                [
                    'label' => __('rates.summary_average'),
                    'value' => $marketAverage,
                    'note' => trans_choice('rates.summary_across', $organizationCount, ['count' => $organizationCount]),
                    'hint' => __('rates.summary_average_hint'),
                    'tone' => 'text-ink',
                ],
            ];
        @endphp

        @if ($rowCount > 0)
            {{--
                Three figures a visitor would otherwise get by reading fourteen
                rows twice - and they stay put whether or not an amount has been
                entered. Swapping them out for the best-rate band the moment a
                number was typed was the single biggest reason this page felt
                like it had jumped to a different screen.
            --}}
                {{-- Three across on a phone too, not stacked: at full height
                these three filled the screen before a single rate appeared, and
                they are a glance rather than a read. The label and the holder
                stand down below sm - the figure is the point, and the popover
                still explains it. --}}
                <div class="mt-8 grid grid-cols-3 gap-2 sm:gap-4 md:grid-cols-3">
                    @foreach ($summaryCards as $card)
                        <div class="flex min-w-0 flex-col rounded-xl border border-placeholder bg-white p-3 text-center sm:p-4 sm:text-start">
                            <span class="flex items-center justify-center gap-1.5 sm:justify-start">
                                <span class="min-w-0 text-[10px] font-semibold tracking-wider break-words text-muted uppercase sm:text-xs">{{ $card['label'] }}</span>
                                <span class="hidden sm:inline-flex">
                                    <x-info-popover :label="$card['label']">{{ $card['hint'] }}</x-info-popover>
                                </span>
                            </span>

                            {{-- Scaled with the viewport: at 36px a five-figure
                            rate plus its unit is wider than a 320px screen, and
                            it is set nowrap so it would push the page rather
                            than break. --}}
                            <p class="mt-1 flex items-end justify-center gap-1 whitespace-nowrap sm:justify-start sm:gap-2">
                                <span class="text-lg font-semibold tracking-tight tabular-nums sm:text-3xl lg:text-4xl {{ $card['tone'] }}">
                                    {{ number_format((float) $card['value'], 2) }}
                                </span>
                                <span class="hidden pb-1.5 text-sm text-muted sm:inline">{{ __('exchange_quotes.request.amd') }}</span>
                            </p>

                            <p class="mt-1 hidden truncate text-sm text-muted sm:block" title="{{ $card['note'] }}">{{ $card['note'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- A link rather than the inline preview chart the brief
                sketches: this page has been trimmed hard, and a chart here
                would be a fourth summary above a table that is the point. --}}
                <p class="mt-3">
                    <a href="{{ route('rates.history', ['currency' => $selectedCurrency?->code]) }}" class="inline-flex min-h-11 items-center text-sm font-medium break-words text-primary hover:underline">
                        {{ __('rates.history.link') }} &rarr;
                    </a>
                </p>

            {{--
                The answer, before the table, and filled rather than tinted -
                a pale strip the width of the page reads as a caption on the
                table instead of the thing the visitor came for.

                Only while calculating: with no amount there is no single
                winner to state, because buy and sell rank in opposite
                directions.
            --}}
            @if ($calculating && $best)
                {{-- A pale card with one saturated edge, rather than a block of
                solid green: filled, it was the loudest thing on a page whose
                point is the table under it, and it left the total set in white
                on green where every other number here is ink on white. --}}
                <div class="relative mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-4 overflow-hidden rounded-2xl border-2 border-primary/40 bg-primary/5 py-5 pr-4 pl-6 sm:pr-6 sm:pl-8">
                    <span class="absolute inset-y-0 left-0 w-2 bg-primary" aria-hidden="true"></span>

                    <div class="flex min-w-0 items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-placeholder bg-white shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-6 w-6 fill-accent-yellow" aria-hidden="true">
                                <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            {{-- "Current" next to "1 day ago" is a contradiction
                            the visitor has to resolve, and they resolve it by
                            trusting the page less. Claim only what is true. --}}
                            <p class="font-heading text-lg font-bold break-words text-ink">
                                {{ $isStale($best->scraped_at) ? __('rates.best_heading_stale') : __('rates.best_heading') }}
                            </p>
                            <p class="text-sm break-words text-muted">
                                @if ($bestRows->count() > 1)
                                    {{ trans_choice('rates.best_shared', $bestRows->count() - 1, [
                                        'name' => $best->organization_name,
                                        'count' => $bestRows->count() - 1,
                                    ]) }}
                                @else
                                    {{ $best->organization_name }}
                                @endif
                                @if ($best->scraped_at)
                                    &middot; {{ Carbon::parse($best->scraped_at)->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="text-end">
                        <p class="{{ $labelClass }}">{{ $totalColumn }}</p>
                        {{-- Scaled down on the narrowest screens: at 36px a
                        six-figure total plus "драм" is wider than 320px, and it
                        is set nowrap so it would push the page rather than
                        break. --}}
                        <p class="mt-1 text-2xl font-bold tracking-tight whitespace-nowrap text-ink tabular-nums sm:text-4xl">
                            {{ $amd($convert((float) $best->{$rateField})) }}
                            <span class="text-base font-normal text-muted sm:text-xl">{{ $targetCode }}</span>
                        </p>

                        {{-- Only when there is a gap to report: with a single
                        organization on the page the average IS the best rate,
                        and "you get 0 more than average" is noise. --}}
                        @if ($averageGain !== null && $averageGain >= 0.01)
                            <p class="mt-1 text-sm break-words text-muted">
                                {{ __('rates.above_average', ['amount' => $amd($averageGain), 'currency' => $targetCode]) }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            {{--
                One thin line, not a heading block. The page title already says
                what this is and the summary above says how it is narrowed, so a
                second "All rates" heading was restating both.
            --}}
            <div class="mt-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                <p class="min-w-0 text-sm break-words text-muted empty:hidden">
                    @if ($hasNonDefaultFilter)
                        <a href="{{ route('rates.index', array_filter(['currency' => $selectedCurrency?->code])) }}" class="-my-2 inline-block py-2 underline hover:text-ink">{{ __('rates.reset_filters') }}</a>
                    @endif
                    {{-- 0.01, not 1: the floor was written for dram totals in
                    the thousands, and silently swallowed the whole line for a
                    small amount of a low-value currency. --}}
                    @if ($marketSaving !== null && $marketSaving >= 0.01)
                        {{-- "difference between best and worst" is a fact about
                        the table; "you keep X by picking the best" is a fact
                        about the visitor. Same number. --}}
                        {{ __('rates.market_saving_sell', [
                            'amount' => $amd($marketSaving),
                            'currency' => $targetCode,
                        ]) }}
                    @endif
                    @if ($allStale)
                        <span class="inline-flex items-center gap-1 text-[#B4791F]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0" aria-hidden="true">
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0" />
                                <path d="M12 9v4" /><path d="M12 17h.01" />
                            </svg>
                            {{ __('rates.all_stale_notice') }}
                        </span>
                    @endif
                </p>

                {{-- List or map. The list is the default and the map is never
                loaded until asked for - see rates-map.js. --}}
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <div class="flex rounded-lg border border-placeholder bg-placeholder/25 p-1">
                        @foreach (['view_list' => null, 'view_map' => 'map'] as $key => $mode)
                            @php $isCurrent = $viewMode === ($mode ?? 'list'); @endphp
                            <a
                                href="{{ $link(['view' => $mode]) }}"
                                aria-current="{{ $isCurrent ? 'true' : 'false' }}"
                                class="inline-flex min-h-9 min-w-0 items-center rounded-md px-3 py-2 text-xs font-medium break-words transition {{ $isCurrent ? 'bg-primary text-white shadow-sm' : 'text-muted hover:text-ink' }}"
                            >
                                {{ __('rates.'.$key) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($viewMode === 'map')
                {{--
                    The map replaces the list rather than sitting beside it: two
                    renderings of the same fourteen rows, both on screen, is the
                    duplication this page has been shedding.

                    Rendered server-side as JSON and handed to a loader that
                    imports Leaflet on demand - the library is 149KB and no
                    other view of this page needs a byte of it.
                --}}
                @if ($mapPoints === [])
                    <div class="mt-4 rounded-xl border border-dashed border-placeholder px-6 py-16 text-center">
                        <p class="text-sm text-muted">{{ __('rates.map_empty') }}</p>
                    </div>
                @else
                    {{-- Mounted by app.js rather than by Alpine: this panel is
                    morphed on every filter click, and a subtree that arrives via
                    morph never gets x-init run over it, so the map silently
                    failed to appear whenever you reached it by pressing "Map"
                    rather than by loading the URL. --}}
                    <div data-rates-map class="mt-4">
                        <script type="application/json" data-rates-map-payload>
                            {!! json_encode([
                                'points' => $mapPoints,
                                'labels' => [
                                    'rate' => __('rates.map_rate_label'),
                                    'total' => __('rates.map_total_label'),
                                    'distance' => __('rates.map_distance_label'),
                                    'directions' => __('rates.directions'),
                                    'negotiate' => __('rates.cta_button'),
                                ],
                            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
                        </script>

                        {{-- A fixed height, because a div with no content has
                        none and Leaflet would render into nothing. --}}
                        <div
                            data-rates-map-canvas
                            class="h-[28rem] w-full overflow-hidden rounded-xl border border-placeholder bg-placeholder/20 sm:h-[34rem]"
                            role="application"
                            aria-label="{{ __('rates.view_map') }}"
                        ></div>
                    </div>
                @endif
            @else
            {{--
                Mobile: a card per organization rather than a squeezed row.

                A phone has no width to spare sideways but plenty of room
                downwards, so the two rates get a column each at full size
                instead of being crushed into the right-hand edge - and the
                things that were fighting for that edge (freshness, directions)
                get a footer of their own.
            --}}
            <div class="mt-4 space-y-3 sm:hidden">
                @foreach ($pageRows as $rate)
                    @php
                        $total = $calculating ? $convert((float) $rate->{$rateField}) : null;
                        $winsMobile = $calculating ? $rate->rank === 1 : $isBestRate((float) $rate->buy_rate, $bestBuy);
                    @endphp

                    <article @class([
                        'relative overflow-hidden rounded-xl p-4',
                        'border-2 border-primary/40 bg-accent-yellow/10' => $winsMobile,
                        'border border-placeholder bg-white' => ! $winsMobile,
                    ])>
                        @if ($winsMobile)
                            <span class="absolute top-0 right-0 rounded-bl-lg bg-primary px-2 py-1 text-[10px] font-bold tracking-wider text-white uppercase">
                                {{ __('rates.best_badge') }}
                            </span>
                        @endif

                        <div class="flex items-center gap-3">
                            <a href="{{ $rate->organization_url }}" class="shrink-0">
                                <x-rates.org-mark :logo="$rate->organization_logo" :name="$rate->organization_name" />
                            </a>

                            <div class="min-w-0 flex-1">
                                <a href="{{ $rate->organization_url }}" class="-my-2 block py-2 pr-16 font-semibold break-words text-ink hover:text-primary">
                                    {{ $rate->organization_name }}
                                </a>
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-muted">
                                    @if ($showMarket)
                                        <span class="break-words">{{ __('rates.market_badge.' . $rate->organization_type) }}</span>
                                    @endif
                                    @if ($hasLocation && isset($rate->distance_km))
                                        @if ($showMarket)<span aria-hidden="true">&middot;</span>@endif
                                        <span>{{ __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) }}</span>
                                    @endif
                                </span>
                                @if ($rate->organization_reviews_count > 0)
                                    <span class="mt-1 flex items-center gap-1">
                                        <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                        <span class="text-xs text-muted">({{ $rate->organization_reviews_count }})</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- The pair at full size, a column each. This is the
                        room a phone actually has. --}}
                        <div class="my-3 grid grid-cols-2 gap-4 border-y border-placeholder py-3">
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wider text-muted uppercase">{{ __('rates.buy_column') }}</span>
                                <span class="mt-0.5 block text-xl font-bold whitespace-nowrap text-primary tabular-nums">{{ number_format($rate->buy_rate, 2) }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wider text-muted uppercase">{{ __('rates.sell_column') }}</span>
                                <span class="mt-0.5 block text-xl font-bold whitespace-nowrap tabular-nums text-accent-red">{{ number_format($rate->sell_rate, 2) }}</span>
                            </div>
                        </div>

                        @if ($calculating)
                            <p class="mb-3 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 rounded-lg bg-placeholder/25 px-3 py-2">
                                <span class="text-xs font-semibold tracking-wider text-muted uppercase">{{ $totalColumn }}</span>
                                <span class="font-bold whitespace-nowrap text-ink tabular-nums">
                                    {{ $amd($total) }} <span class="text-xs font-normal text-muted">{{ $targetCode }}</span>
                                </span>
                            </p>
                        @endif

                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                            @if ($rate->scraped_at)
                                <x-rates.freshness
                                    :scraped-at="$rate->scraped_at"
                                    :stale="$isStale($rate->scraped_at)"
                                    :changed-at="$rate->changed_at ?? null"
                                />
                            @else
                                <span></span>
                            @endif

                            @if ($rate->branch ?? null)
                                <a
                                    href="{{ $rate->branch['url'] }}"
                                    target="_blank" rel="noopener noreferrer"
                                    class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-placeholder px-4 text-xs font-medium break-words text-ink transition hover:border-primary hover:text-primary"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0" aria-hidden="true">
                                        <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                    {{ __('rates.directions') }}
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop table. Three columns: who, the rate, the total. The
            market and the timestamp ride under the name rather than claiming
            columns of their own - they qualify the row, they are not something
            you compare across rows. --}}
            {{-- relative, so the sr-only labels inside resolve against this box: with
            no positioned ancestor they fall back to the page, and an absolutely
            positioned span sitting at x=892 in a scrolled-out column drags the
            whole document sideways. --}}
            <div class="relative mt-4 hidden overflow-x-auto rounded-xl border border-placeholder sm:block">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        {{-- Tinted, so the column names read as the frame around
                        the numbers rather than as a first row of them. --}}
                        <tr class="border-b border-placeholder bg-placeholder/25 text-xs font-semibold tracking-wider text-muted uppercase">
                            <th class="px-6 py-3 text-left">{{ __('rates.provider_column') }}</th>
                            {{-- One table, whatever the visitor has typed. The
                            rate pair is always here, always in the same two
                            places; an amount adds a column, it does not swap the
                            table for a different one. --}}
                            <th class="px-6 py-3 text-right" title="{{ __('rates.buy_hint') }}">
                                <x-rates.sort-heading column="buy" :href="$sortHref('buy')" :active="$activeSortColumn === 'buy'" :direction="$direction">
                                    {{ __('rates.buy_column') }}
                                </x-rates.sort-heading>
                            </th>
                            <th class="px-6 py-3 text-right" title="{{ __('rates.sell_hint') }}">
                                <x-rates.sort-heading column="sell" :href="$sortHref('sell')" :active="$activeSortColumn === 'sell'" :direction="$direction">
                                    {{ __('rates.sell_column') }}
                                </x-rates.sort-heading>
                            </th>

                            {{-- The gap between the two columns printed either
                            side of it. Always here now that it can be sorted
                            on: a column you can rank the table by is not one
                            to hide behind a view toggle. --}}
                            <th class="hidden px-4 py-3 text-right lg:table-cell" title="{{ __('rates.spread_hint') }}">
                                <x-rates.sort-heading column="spread" :href="$sortHref('spread')" :active="$activeSortColumn === 'spread'" :direction="$direction">
                                    {{ __('rates.spread_column') }}
                                </x-rates.sort-heading>
                            </th>

                            @if ($calculating)
                                {{-- Tinted, because this is the number the
                                calculation exists to produce and it sits
                                furthest from the name. --}}
                                <th class="bg-placeholder/25 px-6 py-3 text-right">
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ $totalColumn }}
                                        <x-info-popover :label="$totalColumn">
                                            {{ __($isBuying ? 'rates.rate_column_hint_buy' : 'rates.rate_column_hint_sell', ['code' => $selectedCurrency?->code]) }}
                                        </x-info-popover>
                                    </span>
                                </th>
                            @endif

                            {{-- Out of the line under the name and into a column
                            of its own once the width allows one, so freshness
                            can be compared down the list rather than read row by
                            row. --}}
                            <th class="hidden px-4 py-3 text-right md:table-cell">
                                <x-rates.sort-heading column="updated" :href="$sortHref('updated')" :active="$activeSortColumn === 'updated'" :direction="$direction">
                                    {{ __('rates.updated_column') }}
                                </x-rates.sort-heading>
                            </th>

                            {{-- Unlabelled: the pin says it, and a heading over
                            a 40px button would be wider than the column. --}}
                            <th class="px-4 py-3"><span class="sr-only">{{ __('rates.directions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pageRows as $rate)
                            @php $total = $calculating ? $convert((float) $rate->{$rateField}) : null; @endphp
                            <tr class="border-b border-placeholder last:border-b-0 hover:bg-placeholder/15">
                                <td class="px-6 py-4">
                                    {{-- See the mobile card: the meta line holds
                                    a link now, so the cell cannot be one. --}}
                                    <div class="flex items-center gap-3">
                                        <a href="{{ $rate->organization_url }}" class="shrink-0">
                                            <x-rates.org-mark :logo="$rate->organization_logo" :name="$rate->organization_name" />
                                        </a>
                                        <div class="min-w-0">
                                            {{-- No star here: with an amount the
                                            total column marks the winner, and
                                            without one each rate column marks
                                            its own. One star per row, always on
                                            the number it is about. --}}
                                            <a href="{{ $rate->organization_url }}" class="block truncate font-medium text-ink hover:text-primary">{{ $rate->organization_name }}</a>
                                            {{-- No directions here: the row
                                            ends with a button for that. The
                                            timestamp hides itself once its own
                                            column appears. --}}
                                            <x-rates.org-meta
                                                :market="$showMarket ? __('rates.market_badge.' . $rate->organization_type) : null"
                                                :scraped-at="$rate->scraped_at"
                                                :stale="$isStale($rate->scraped_at)"
                                                :changed-at="$rate->changed_at ?? null"
                                                :distance="$hasLocation && isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : null"
                                                timestamp-class="md:hidden"
                                            />
                                            @if ($rate->organization_reviews_count > 0)
                                                <span class="mt-1 flex items-center gap-1">
                                                    <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                                    <span class="text-xs text-muted">({{ $rate->organization_reviews_count }})</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @php
                                    // With an amount on screen the answer is the
                                    // total, so that is the column that carries
                                    // the star. Without one there is no single
                                    // winner - buy and sell rank in opposite
                                    // directions - so each column marks its own.
                                    $winsBuy = ! $calculating && $isBestRate((float) $rate->buy_rate, $bestBuy);
                                    $winsSell = ! $calculating && $isBestRate((float) $rate->sell_rate, $bestSell);
                                    $winsTotal = $calculating && $rate->rank === 1;
                                @endphp

                                {{-- The same green as the buy-side summary card,
                                against the red on sell: the pair reads as two
                                sides in the table exactly as it does above it.
                                The winning figure is bolder as well as starred. --}}
                                <td @class(['px-6 py-4 text-right text-base text-primary tabular-nums', 'font-semibold' => $winsBuy, 'font-medium' => ! $winsBuy])>
                                    <span class="inline-flex items-center justify-end gap-2">
                                        @if ($winsBuy)
                                            <x-rates.best-chip :count="$bestBuyCount" />
                                        @endif
                                        {{ number_format($rate->buy_rate, 2) }}
                                    </span>
                                </td>
                                <td @class(['px-6 py-4 text-right text-base tabular-nums text-accent-red', 'font-semibold' => $winsSell, 'font-medium' => ! $winsSell])>
                                    <span class="inline-flex items-center justify-end gap-2">
                                        @if ($winsSell)
                                            <x-rates.best-chip :count="$bestSellCount" />
                                        @endif
                                        {{ number_format($rate->sell_rate, 2) }}
                                    </span>
                                </td>

                                <td class="hidden px-4 py-4 text-right text-muted tabular-nums lg:table-cell">
                                    {{ number_format((float) $rate->sell_rate - (float) $rate->buy_rate, 2) }}
                                </td>

                                @if ($calculating)
                                    <td @class(['bg-placeholder/25 px-6 py-4 text-right text-base whitespace-nowrap text-ink tabular-nums', 'font-bold' => $winsTotal, 'font-medium' => ! $winsTotal])>
                                        <span class="inline-flex items-center justify-end gap-2">
                                            @if ($winsTotal)
                                                <x-rates.best-chip :count="$bestTotalCount" />
                                            @endif
                                            <span>
                                                {{ $amd($total) }}
                                                <span class="text-xs font-normal text-muted">{{ $targetCode }}</span>
                                            </span>
                                        </span>
                                    </td>
                                @endif

                                <td class="hidden px-4 py-4 text-right text-xs whitespace-nowrap md:table-cell">
                                    @if ($rate->scraped_at)
                                        <x-rates.freshness :scraped-at="$rate->scraped_at" :stale="$isStale($rate->scraped_at)" :changed-at="$rate->changed_at ?? null" />
                                    @else
                                        <span class="text-muted" aria-hidden="true">&mdash;</span>
                                    @endif
                                </td>

                                {{-- Nobody comes here to read a rate; they come
                                to go and exchange money. This is the last step
                                of that, so it is a real link out to whatever maps
                                app the device has, not another page of ours. --}}
                                <td class="px-4 py-4">
                                    @if ($rate->branch ?? null)
                                        <a
                                            href="{{ $rate->branch['url'] }}"
                                            target="_blank" rel="noopener noreferrer"
                                            title="{{ $rate->branch['address'] ?: $rate->branch['name'] }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary hover:text-white"
                                        >
                                            <span class="sr-only">{{ __('rates.directions') }}</span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                                <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                                                <circle cx="12" cy="10" r="3" />
                                            </svg>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- The map is not paged: it plots every branch behind every rate
            these filters match, because a map showing ten of fourteen offices
            is a map that hides four of them. Page numbers under it would say
            otherwise. --}}
            @if ($viewMode !== 'map')
                <x-rates.pagination :paginator="$rates" />
            @endif
        @else
            {{-- Never a dead end: offer the nearest combination that has data
            rather than only reporting the absence. --}}
            <div class="mt-8 rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
                {{-- A search that found nothing is a different miss from a
                filter combination with no rows, and suggesting a rate type
                would be answering a question nobody asked. --}}
                <p class="text-sm break-words text-muted">
                    {{ $search !== '' ? __('rates.search_empty', ['term' => $search]) : __('rates.no_rates_match') }}
                </p>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    @if ($search !== '')
                        <a href="{{ $link(['q' => null]) }}" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:bg-primary-dark">
                            {{ __('rates.search_clear') }}
                        </a>
                    @elseif ($suggestedType)
                        <a href="{{ $link(['type' => $suggestedType]) }}" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:bg-primary-dark">
                            {{ __('rates.try_other_type', ['type' => __('organizations.rate_types.' . $suggestedType)]) }}
                        </a>
                    @endif
                    @if ($hasNonDefaultFilter)
                        <a href="{{ route('rates.index', array_filter(['currency' => $selectedCurrency?->code])) }}" class="text-sm font-medium text-primary hover:underline">
                            {{ __('rates.reset_filters') }}
                        </a>
                    @endif
                </div>
            </div>
        @endif


        <p class="mt-8 border-t border-placeholder pt-5 text-xs leading-relaxed break-words text-muted">
            {{ __('rates.disclaimer') }}
        </p>

    </section>

    {{-- Outside the panel: it is morphed on every filter click, and a dialog
    patched underneath an open form would lose what was typed into it. --}}
    <x-rate-alert-modal
        :currencies="$currencies"
        :organizations="$alertOrganizations"
        :rate-types="$alertRateTypes"
    />

    <x-better-rate-modal :currencies="$quoteCurrencies" :cities="$cities->all()" />
    {{--
        Filtering used to reload the whole document - re-parsing every asset,
        losing scroll position and flashing the header on each pill. This swaps
        only #rates-panel from the same URL the link already points at, so the
        page stays put and the address bar still holds shareable state.

        Progressive enhancement on purpose: every control here is a real link or
        a GET form, so with JS off, or if any fetch fails, the browser just
        navigates as before. Nothing depends on this script to work.
    --}}
    <script>
        (() => {
            const panel = document.getElementById('rates-panel');
            if (!panel || !window.fetch || !window.history.pushState || !window.DOMParser) {
                return;
            }

            let inFlight = null;

            const swap = async (url, push) => {
                inFlight?.abort();
                const request = new AbortController();
                inFlight = request;

                panel.setAttribute('aria-busy', 'true');

                // Morph can land the patched control on a fresh node, which
                // drops focus to <body> - so a keyboard user who picks a city
                // loses their place. Remembered by id and restored after.
                const focusedId = document.activeElement?.id || null;

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'fetch' },
                        signal: request.signal,
                    });
                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    const next = new DOMParser()
                        .parseFromString(await response.text(), 'text/html')
                        .getElementById('rates-panel');
                    if (!next) {
                        throw new Error('no panel in response');
                    }

                    // Morph, not innerHTML: patching only the nodes that
                    // actually differ means the untouched parts of the page
                    // never repaint, so there is no flash and no scroll jump.
                    // Replacing the subtree also tore down every Alpine
                    // component inside it on each click.
                    window.Alpine.morph(panel, next.outerHTML);

                    if (focusedId) {
                        document.getElementById(focusedId)?.focus({ preventScroll: true });
                    }

                    // Anything that has to mount itself into the swapped panel
                    // gets told the swap is done - Alpine does not initialise a
                    // morphed-in subtree, so the map cannot rely on x-init.
                    window.dispatchEvent(new CustomEvent('rates:panel-updated'));

                    if (push) {
                        window.history.pushState({ ratesUrl: url }, '', url);
                    }
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    window.location.assign(url);
                    return;
                } finally {
                    if (inFlight === request) {
                        inFlight = null;
                        panel.removeAttribute('aria-busy');
                    }
                }
            };

            // Only links that stay on this page. Organization rows, the alert
            // and the negotiate CTA all point elsewhere and navigate normally.
            panel.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                const link = event.target.closest('a[href]');
                if (!link || link.target) {
                    return;
                }
                const target = new URL(link.href, window.location.href);
                if (target.origin !== window.location.origin || target.pathname !== window.location.pathname) {
                    return;
                }
                event.preventDefault();
                swap(target.href, true);
            });

            panel.addEventListener('submit', (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') {
                    return;
                }
                const target = new URL(form.action, window.location.href);
                if (target.pathname !== window.location.pathname) {
                    return;
                }
                event.preventDefault();

                // Empty selects would otherwise litter the URL with bare keys.
                const params = new URLSearchParams();
                for (const [key, value] of new FormData(form)) {
                    if (value !== '') {
                        params.append(key, value);
                    }
                }
                target.search = params.toString();
                swap(target.href, true);
            });

            window.addEventListener('rates:navigate', (event) => swap(event.detail, true));
            window.addEventListener('popstate', () => swap(window.location.href, false));
        })();
    </script>
@endsection
