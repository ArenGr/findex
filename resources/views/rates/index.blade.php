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
        'direction' => $direction,
        'lat' => $latitude,
        'lng' => $longitude,
        'intent' => $intent,
        'amount' => $amount,
        'both' => $showBothRates ? 1 : null,
    ];

    $link = fn (array $overrides = []) => route('rates.index', array_filter(
        [...$baseParams, 'currency' => $selectedCurrency?->code, ...$overrides],
        fn ($value) => $value !== null && $value !== '',
    ));

    $hasNonDefaultFilter = $selectedType !== \App\Enums\RateType::CASH
        || $selectedOrgType || $selectedOrganization || $selectedCity || $hasLocation
        || $amount !== null;

    // Whole dram above 1,000, where a rounded half-unit is noise. Below it the
    // decimals are the number: 1 KZT at 4.60 rendered as "5", and the office
    // beside it quoting 4.75 rendered as "5" too, so the table stopped telling
    // two rows apart at exactly the amounts where the gap is easiest to see.
    $amd = fn (float $value) => $value < 1000
        ? number_format($value, 2)
        : number_format($value);

    // Two views of the same rows. Without an amount the page answers "what are
    // today's rates" with the Buy/Sell table everyone in this market already
    // reads. Type an amount and it answers "what do I get", which needs a
    // direction, a single rate column and a total - a different table, not a
    // decoration on this one.
    $calculating = $amount !== null;

    // Which column the visitor's intent ranks on, and therefore which one the
    // "you pay / you get" total is derived from.
    $rateField = \App\Http\Controllers\RateController::rateFieldForIntent($intent);
    $isBuying = $intent === 'buy';

    // A rate this old is worth flagging - banks republish through the day, so
    // anything past a day is no longer "today's rate".
    $staleAfterHours = 24;
    $isStale = fn ($scrapedAt) => $scrapedAt && Carbon::parse($scrapedAt)->diffInHours(now()) >= $staleAfterHours;

    $rowCount = $ranked['count'];
    $allStale = $rowCount > 0 && collect($ranked['rows'])->every(fn ($row) => $isStale($row->scraped_at));

    // Shown once above the table rather than per market, now that everything
    // is ranked as one list.
    $marketSaving = $calculating && $ranked['spread'] !== null ? $amount * $ranked['spread'] : null;

    // Banks and exchange offices share one table, so each row says which it is
    // - but only when the list actually mixes them.
    $showMarket = collect($ranked['rows'])->pluck('organization_type')->unique()->count() > 1;


    $labelClass = 'block text-xs font-semibold tracking-wider text-muted uppercase';

    $activeFilterCount = collect([$selectedOrganization, $selectedCity, $hasLocation ?: null])
        ->filter()->count();
@endphp

@section('content')
    <section id="rates-panel" class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold break-words text-ink lg:text-3xl">{{ __('rates.all_heading') }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('rates.all_subheading') }}</p>
            </div>

            {{-- The one thing here a competitor cannot offer, so it takes the
            prime slot beside the heading. The "who is this for" detail lives in
            a popover rather than a paragraph - it only matters to the people
            who stop to ask. --}}
            @if ($quoteMinimum !== null)
                @php $qualifies = $amount >= $quoteMinimum; @endphp
                <div class="flex items-center gap-2 sm:shrink-0">
                    <a
                        href="{{ route('exchange.request', array_filter(['currency' => $selectedCurrency?->code, 'amount' => $qualifies ? $amount : null])) }}"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-ink hover:text-primary"
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
                            class="h-5 w-5 shrink-0 text-accent-yellow" aria-hidden="true"
                        >
                            <path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z" />
                            <path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1" />
                        </svg>
                        <span class="min-w-0 break-words">{{ __('rates.cta_button') }}</span>
                    </a>

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
                </div>
            @endif
        </div>

        {{--
            Currency, direction and amount are the three decisions that define a
            result, so they sit together in one card and are answered top to
            bottom. Everything below the card only narrows what the card already
            asked for.

            Currency is a set of links inside the form rather than a field:
            picking one navigates, carrying the current amount and intent.
        --}}
        <form method="GET" action="{{ route('rates.index') }}" class="mt-8 rounded-2xl border border-placeholder bg-white p-5">
            @foreach (['type', 'org_type', 'organization', 'city', 'lat', 'lng'] as $carried)
                @if (!empty($baseParams[$carried]))
                    <input type="hidden" name="{{ $carried }}" value="{{ $baseParams[$carried] }}">
                @endif
            @endforeach
            <input type="hidden" name="currency" value="{{ $selectedCurrency?->code }}">

            <span class="{{ $labelClass }}">{{ __('rates.currency_label') }}</span>
            {{-- Eleven currencies wrap to six rows on a phone and push the
            amount field off screen, so on small viewports they scroll sideways
            instead. --}}
            <div class="mt-2 flex gap-2 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:overflow-visible [&::-webkit-scrollbar]:hidden">
                @foreach ($currencies as $currency)
                    <a
                        href="{{ $link(['currency' => $currency->code]) }}"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold tracking-wide uppercase transition {{ $selectedCurrency?->id === $currency->id ? 'border-border-muted bg-placeholder/40 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                    >
                        <span aria-hidden="true" class="text-base">{{ \App\Models\Currency::flag($currency->code) }}</span>
                        {{ $currency->code }}
                    </a>
                @endforeach
            </div>

            {{--
                The calculator, which is a second question rather than the
                point of the page - so it sits in its own panel, on one line,
                and produces a different table when answered.

                Alpine-only, deliberately: with JS off the buy/sell radios do
                not self-submit, so the button is the only way to apply an
                intent change and must stay enabled.
            --}}
            <div x-data="{ amount: @js($amount ?? '') }" class="mt-4 rounded-xl bg-placeholder/25 px-4 py-4">
                <span class="{{ $labelClass }}">{{ __('rates.calculator_label') }}</span>

                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-3">
                    {{-- Full width below sm, halves that share it: "Վաճառել
                    USD" is wider than a 320px screen leaves for a content-sized
                    pill, and shrink-0 turned that into page overflow rather than
                    a wrap. --}}
                    <div class="flex w-full rounded-lg bg-white p-1 sm:w-auto sm:shrink-0">
                        @foreach (['buy', 'sell'] as $option)
                            <label class="min-w-0 flex-1 cursor-pointer sm:flex-none">
                                {{-- Submits on pick, but only once there is an
                                amount to recalculate: switching direction on the
                                plain rate table changes nothing a visitor can
                                see, and reloading under them looks like a fault. --}}
                                <input
                                    type="radio" name="intent" value="{{ $option }}"
                                    class="peer sr-only" @checked($intent === $option)
                                    onchange="if (Number(this.form.amount.value) > 0) { this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit(); }"
                                >
                                <span class="block rounded-md px-3 py-2.5 text-center text-sm leading-tight font-semibold break-words text-muted transition peer-checked:bg-primary peer-checked:text-white sm:px-5">
                                    {{ __('rates.intent_'.$option, ['currency' => $selectedCurrency?->code]) }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <label for="amount" class="sr-only">{{ __('rates.amount_label') }}</label>
                        <input
                            type="number" inputmode="decimal" step="0.01" min="0"
                            name="amount" id="amount"
                            x-model="amount"
                            value="{{ $amount }}"
                            placeholder="{{ __('rates.amount_placeholder') }}"
                            class="w-full min-w-0 rounded-lg border border-border-muted bg-white px-4 py-2.5 text-base text-ink focus:border-primary focus:outline-none"
                        >
                        <span class="shrink-0 text-sm font-semibold text-ink">{{ $selectedCurrency?->code }}</span>
                    </div>

                    {{-- Number(), not a truthiness check: the string "0" is
                    truthy in JS, and the controller drops a zero amount
                    anyway. --}}
                    <button
                        type="submit"
                        :disabled="!(Number(amount) > 0)"
                        {{-- Its own line on a phone: sharing one with the
                        amount field left the field about 90px wide, which
                        truncates a four-figure number as you type it. --}}
                        class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:bg-placeholder disabled:text-muted disabled:hover:bg-placeholder sm:w-auto sm:shrink-0"
                    >
                        {{ __('rates.calculate_submit') }}
                    </button>

                    @if ($calculating)
                        {{-- Not shrink-0: the Armenian string is wider than a
                        320px screen and would push the page sideways. --}}
                        <a href="{{ $link(['amount' => null]) }}" class="min-w-0 text-sm break-words text-muted underline hover:text-ink">
                            {{ __('rates.calculator_clear') }}
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{--
            The three narrowing filters read as one row of questions - where,
            what kind, which city - rather than three stacked blocks each
            claiming its own band of the page. They wrap in order on a phone.
        --}}
        <div class="mt-6 flex flex-wrap items-start gap-x-10 gap-y-5">

        {{-- Market tabs. Banks and exchange offices quote very different
        levels, so they are separated rather than interleaved. --}}
        @if ($orgTypes->count() > 1)
            <div>
                <span class="{{ $labelClass }}">{{ __('rates.market_label') }}</span>
                <div class="mt-2 flex flex-wrap gap-2">
                <a
                    href="{{ $link(['org_type' => null, 'organization' => null]) }}"
                    class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $selectedOrgType === null ? 'border-border-muted bg-placeholder/40 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                >
                    {{ __('rates.market_all') }}
                </a>
                @foreach ($orgTypes as $orgType)
                    <a
                        href="{{ $link(['org_type' => $orgType, 'organization' => null]) }}"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $selectedOrgType === $orgType ? 'border-border-muted bg-placeholder/40 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                    >
                        {{ __('rates.markets.' . $orgType) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Transaction type: only the ones this currency actually has, so a
        pill never leads to an empty table. --}}
        <div>
            <span class="{{ $labelClass }}">{{ __('rates.type_label') }}</span>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($availableTypes as $typeValue)
                    <a
                        href="{{ $link(['type' => $typeValue]) }}"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $selectedType->value === $typeValue ? 'border-border-muted bg-placeholder/40 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                    >
                        {{ __('organizations.rate_types.' . $typeValue) }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Secondary filters, collapsed behind a toggle on mobile so they
        don't push the results themselves below the fold. --}}
        <div x-data="{ open: window.__ratesFiltersOpen ?? false }" x-effect="window.__ratesFiltersOpen = open">
            <button
                type="button"
                @click="open = !open"
                class="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted uppercase sm:hidden"
                :aria-expanded="open"
            >
                {{ __('rates.more_filters') }}@if ($activeFilterCount) ({{ $activeFilterCount }}) @endif
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                    <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div x-show="open" x-cloak class="mt-3 flex flex-wrap items-end gap-x-3 gap-y-3 sm:!flex sm:mt-0">
                <form method="GET" action="{{ route('rates.index') }}" class="contents">
                    <input type="hidden" name="currency" value="{{ $selectedCurrency?->code }}">
                    <input type="hidden" name="type" value="{{ $selectedType->value }}">
                    <input type="hidden" name="org_type" value="{{ $selectedOrgType }}">
                    <input type="hidden" name="intent" value="{{ $intent }}">
                    {{-- lat/lng were previously omitted here, so changing bank or
                    city silently dropped an active "find nearby". --}}
                    <input type="hidden" name="amount" value="{{ $amount }}">
                    @if ($hasLocation)
                        <input type="hidden" name="lat" value="{{ $latitude }}">
                        <input type="hidden" name="lng" value="{{ $longitude }}">
                    @endif

                    {{-- Only once a market is chosen. Under "All" the list mixes
                    banks and exchange offices, and no single label is honest about
                    what it contains. --}}
                    @if ($selectedOrgType !== null)
                        <div>
                            <label for="filter-organization" class="{{ $labelClass }}">
                                {{ __('rates.filter_org.'.$selectedOrgType) }}
                            </label>
                            <select
                                name="organization"
                                id="filter-organization"
                                onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                class="mt-2 rounded-full border border-placeholder bg-white px-4 py-2 text-sm font-medium text-ink focus:border-primary focus:outline-none"
                            >
                                <option value="">{{ __('rates.filter_org_all.'.$selectedOrgType) }}</option>
                                @foreach ($organizations as $organization)
                                    <option value="{{ $organization->slug }}" @selected($selectedOrganization?->id === $organization->id)>
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($cities->isNotEmpty())
                        <div>
                            <label for="filter-city" class="{{ $labelClass }}">{{ __('rates.filter_city') }}</label>
                            <select
                                name="city"
                                id="filter-city"
                                onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                title="{{ __('rates.filter_city_hint') }}"
                                class="mt-2 rounded-full border border-placeholder bg-white px-4 py-2 text-sm font-medium text-ink focus:border-primary focus:outline-none"
                            >
                                <option value="">{{ __('rates.filter_city_all') }}</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}" @selected($selectedCity === $city)>{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </form>

                {{--
                    Client-side only - the server can't know the visitor's
                    coordinates until the browser's own prompt hands them over.
                    Once granted, reloads the current URL with lat/lng merged
                    in, keeping every other filter intact.
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
                            href="{{ $link(['lat' => null, 'lng' => null, 'sort' => null, 'direction' => null]) }}"
                            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-medium text-primary"
                        >
                            📍 {{ __('rates.nearby_active') }}
                            <span aria-hidden="true">&times;</span>
                        </a>
                    @else
                        <button
                            type="button"
                            @click="findNearby()"
                            :disabled="state === 'locating'"
                            class="inline-flex items-center gap-1 rounded-full border border-placeholder bg-white px-4 py-2 text-sm font-medium text-ink hover:border-primary disabled:opacity-60"
                        >
                            <span x-show="state !== 'locating'">📍 {{ __('rates.find_nearby') }}</span>
                            <span x-show="state === 'locating'" x-cloak>{{ __('rates.locating') }}</span>
                        </button>
                        <p x-show="state === 'error'" x-cloak class="mt-1 text-xs text-red-600">{{ __('rates.location_error') }}</p>
                    @endif
                </div>

            </div>
        </div>

        </div>

        @if ($centralBankRate)
            {{-- Stated once, as a reference. It used to sit in the transaction
            type row as a peer of Cash and Card, which sent visitors to rows
            they could not act on. --}}
            <p class="mt-5 text-sm break-words text-muted">
                {{ __('rates.central_bank_reference', [
                    'rate' => number_format((float) $centralBankRate['rate'], 2),
                    'code' => $selectedCurrency?->code,
                ]) }}
            </p>
        @endif

        @php
            // Ascending first, flipping on a repeat click of the same column.
            $sortLink = fn (string $column) => $link([
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]);
            $sortArrow = fn (string $column) => $sort === $column ? ($direction === 'asc' ? ' ▲' : ' ▼') : '';

            // Rank is computed independently of the chosen sort, so the winner
            // is the same row whichever column the visitor ordered by.
            $bestRows = collect($ranked['rows'])->where('rank', 1);
            $best = $bestRows->first();

            // Without an amount there is no single winner - buy and sell rank
            // in opposite directions - so each column names its own. The
            // organization buys low and sells high, which for the visitor is
            // the highest buy and the lowest sell.
            $bestBuy = collect($ranked['rows'])->max(fn ($row) => (float) $row->buy_rate);
            $bestSell = collect($ranked['rows'])->min(fn ($row) => (float) $row->sell_rate);
            $isBestRate = fn (float $value, ?float $target) => $target !== null && abs($value - $target) < 0.00005;

            $rateColumn = __('rates.rate_column', ['code' => $selectedCurrency?->code]);
            $totalColumn = __($isBuying ? 'rates.total_pay_column' : 'rates.total_get_column');

            $sectionHeading = match ($selectedOrgType) {
                'bank' => __('rates.section_bank'),
                'exchange' => __('rates.section_exchange'),
                default => __('rates.section_all'),
            };
        @endphp

        @if ($rowCount > 0)
            {{--
                The answer, before the table, and filled rather than tinted -
                a pale strip the width of the page reads as a caption on the
                table instead of the thing the visitor came for.

                Only while calculating: with no amount there is no single
                winner to state, because buy and sell rank in opposite
                directions.
            --}}
            @if ($calculating && $best)
                <div class="mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-4 rounded-2xl bg-primary px-4 py-5 sm:px-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-white">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-6 w-6 fill-accent-yellow" aria-hidden="true">
                                <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="font-heading text-lg font-bold break-words text-white">{{ __('rates.best_heading') }}</p>
                            <p class="text-sm break-words text-white/80">
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
                        <p class="block text-xs font-semibold tracking-wider text-white/80 uppercase">{{ $totalColumn }}</p>
                        {{-- Scaled down on the narrowest screens: at 30px a
                        six-figure total plus "драм" is wider than 320px, and it
                        is set nowrap so it would push the page rather than
                        break. --}}
                        <p class="font-heading text-2xl font-bold whitespace-nowrap text-white sm:text-3xl">
                            {{ $amd($amount * (float) $best->{$rateField}) }}
                            <span class="text-base font-normal text-white/80 sm:text-lg">{{ __('exchange_quotes.request.amd') }}</span>
                        </p>
                    </div>
                </div>
            @endif

            <div class="mt-8 flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
                <div class="min-w-0">
                    <h2 class="font-heading text-lg font-bold break-words text-ink">{{ $sectionHeading }}</h2>
                    <p class="mt-1 text-sm break-words text-muted">
                        {{ trans_choice('rates.results_count', $rowCount, ['count' => $rowCount]) }}
                        {{-- Here rather than in the filter row above: appearing
                        there pushed that row onto a second line, so the layout
                        jumped at the moment a filter was applied. It also reads
                        better against a count it is about to change. --}}
                        @if ($hasNonDefaultFilter)
                            &middot; <a href="{{ route('rates.index', array_filter(['currency' => $selectedCurrency?->code])) }}" class="underline hover:text-ink">{{ __('rates.reset_filters') }}</a>
                        @endif
                        {{-- 0.01, not 1: the floor was written for dram totals
                        in the thousands, and silently swallowed the whole line
                        for a small amount of a low-value currency. --}}
                        @if ($marketSaving !== null && $marketSaving >= 0.01)
                            &middot; {{ __('rates.market_saving', [
                                'amount' => $amd($marketSaving),
                                'currency' => __('exchange_quotes.request.amd'),
                            ]) }}
                        @endif
                    </p>
                    @if ($allStale)
                        <p class="mt-1 text-sm break-words text-[#B4791F]">{{ __('rates.all_stale_notice') }}</p>
                    @endif
                </div>

                {{-- Sits with the results rather than in the page header: an
                alert is a follow-up to what you are looking at. --}}
                <div class="flex min-w-0 flex-col items-start gap-1 sm:items-end">
                    <div class="flex items-center gap-2">
                        {{--
                            Opens the modal rather than leaving for /alerts. The
                            page already knows the currency, the transaction type,
                            the buy/sell direction and the going rate, so the form
                            arrives answered and the visitor keeps their filters.

                            A real link underneath: with JS off, or before Alpine
                            boots, this still navigates to the alerts page.
                        --}}
                        <a
                            href="{{ route('alerts.index', array_filter(['currency_id' => $selectedCurrency?->id])) }}#create-alert"
                            onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('rate-alert-open', { detail: {{ Js::from([
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
                            ]) }} }))"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-ink hover:text-primary"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 shrink-0 text-accent-yellow">
                                <path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" clip-rule="evenodd" />
                            </svg>
                            <span class="min-w-0 break-words">{{ __('rates.alert_cta') }}</span>
                        </a>

                        <x-info-popover :label="__('rates.alert_cta')">
                            {{ __('rates.alert_hint') }}
                        </x-info-popover>
                    </div>

                    {{-- Only offered while calculating: that table shows one
                    rate named from the visitor's side, because reading the pair
                    correctly means knowing that "Buy" is the rate the bank buys
                    at, not the one you buy at. The plain table is the pair. --}}
                    @if ($calculating)
                        <a href="{{ $link(['both' => $showBothRates ? null : 1]) }}" class="text-sm break-words text-muted underline hover:text-ink">
                            {{ $showBothRates ? __('rates.hide_both_rates') : __('rates.show_both_rates') }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Mobile: a row list. A table has no room for a readable name
            once the rate and the total both need space. --}}
            <div class="mt-4 overflow-hidden rounded-xl border border-placeholder sm:hidden">
                @foreach ($ranked['rows'] as $rate)
                    @php $total = $calculating ? $amount * (float) $rate->{$rateField} : null; @endphp
                    <a href="{{ $rate->organization_url }}" class="flex items-center gap-3 border-b border-placeholder px-4 py-4 last:border-b-0">
                        <x-rates.org-mark :logo="$rate->organization_logo" :name="$rate->organization_name" />

                        <div class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-medium break-words text-ink">{{ $rate->organization_name }}</span>
                                @if ($calculating && $rate->rank === 1)
                                    <x-rates.best-chip />
                                @endif
                            </span>
                            <x-rates.org-meta
                                :market="$showMarket ? __('rates.market_badge.' . $rate->organization_type) : null"
                                :scraped-at="$rate->scraped_at"
                                :stale="$isStale($rate->scraped_at)"
                                :distance="$hasLocation && isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : null"
                            />
                            @if ($rate->organization_reviews_count > 0)
                                <span class="mt-1 flex items-center gap-1">
                                    <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                    <span class="text-xs text-muted">({{ $rate->organization_reviews_count }})</span>
                                </span>
                            @endif
                        </div>

                        <div class="shrink-0 text-end">
                            @if ($calculating)
                                <p class="font-heading font-bold whitespace-nowrap text-ink">
                                    {{ $amd($total) }}
                                    <span class="text-xs font-normal text-muted">{{ __('exchange_quotes.request.amd') }}</span>
                                </p>
                                <p class="text-xs whitespace-nowrap text-muted">{{ number_format($rate->{$rateField}, 2) }}</p>
                            @else
                                <p class="font-heading font-bold whitespace-nowrap text-ink">{{ number_format($rate->buy_rate, 2) }}</p>
                                <p class="font-heading font-bold whitespace-nowrap text-ink">{{ number_format($rate->sell_rate, 2) }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Desktop table. Three columns: who, the rate, the total. The
            market and the timestamp ride under the name rather than claiming
            columns of their own - they qualify the row, they are not something
            you compare across rows. --}}
            <div class="mt-4 hidden overflow-x-auto rounded-xl border border-placeholder sm:block">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-placeholder text-xs font-semibold tracking-wider text-muted uppercase">
                            <th class="px-6 py-3 text-left">{{ __('rates.provider_column') }}</th>
                            @if ($calculating)
                                <th class="px-4 py-3 text-right">
                                    <a href="{{ $sortLink($rateField) }}" class="hover:text-ink">{{ $rateColumn }}{{ $sortArrow($rateField) }}</a>
                                </th>
                                @if ($showBothRates)
                                    <th class="px-4 py-3 text-right">
                                        <a href="{{ $sortLink('buy_rate') }}" class="hover:text-ink" title="{{ __('rates.buy_hint') }}">{{ __('rates.buy_column') }}{{ $sortArrow('buy_rate') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        <a href="{{ $sortLink('sell_rate') }}" class="hover:text-ink" title="{{ __('rates.sell_hint') }}">{{ __('rates.sell_column') }}{{ $sortArrow('sell_rate') }}</a>
                                    </th>
                                @endif
                                {{-- Tinted, because this is the number the
                                calculation exists to produce and it sits
                                furthest from the name. --}}
                                <th class="bg-placeholder/25 px-6 py-3 text-right">{{ $totalColumn }}</th>
                            @else
                                <th class="px-6 py-3 text-right">
                                    <a href="{{ $sortLink('buy_rate') }}" class="hover:text-ink" title="{{ __('rates.buy_hint') }}">{{ __('rates.buy_column') }}{{ $sortArrow('buy_rate') }}</a>
                                </th>
                                <th class="px-6 py-3 text-right">
                                    <a href="{{ $sortLink('sell_rate') }}" class="hover:text-ink" title="{{ __('rates.sell_hint') }}">{{ __('rates.sell_column') }}{{ $sortArrow('sell_rate') }}</a>
                                </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ranked['rows'] as $rate)
                            @php $total = $calculating ? $amount * (float) $rate->{$rateField} : null; @endphp
                            <tr class="border-b border-placeholder last:border-b-0 hover:bg-placeholder/15">
                                <td class="px-6 py-4">
                                    <a href="{{ $rate->organization_url }}" class="flex items-center gap-3">
                                        <x-rates.org-mark :logo="$rate->organization_logo" :name="$rate->organization_name" />
                                        <div class="min-w-0">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate font-medium text-ink hover:text-primary">{{ $rate->organization_name }}</span>
                                                @if ($calculating && $rate->rank === 1)
                                                    <x-rates.best-chip />
                                                @endif
                                            </span>
                                            <x-rates.org-meta
                                                :market="$showMarket ? __('rates.market_badge.' . $rate->organization_type) : null"
                                                :scraped-at="$rate->scraped_at"
                                                :stale="$isStale($rate->scraped_at)"
                                                :distance="$hasLocation && isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : null"
                                            />
                                            @if ($rate->organization_reviews_count > 0)
                                                <span class="mt-1 flex items-center gap-1">
                                                    <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                                    <span class="text-xs text-muted">({{ $rate->organization_reviews_count }})</span>
                                                </span>
                                            @endif
                                        </div>
                                    </a>
                                </td>
                                @if ($calculating)
                                    <td class="px-4 py-4 text-right font-heading text-base text-ink">
                                        {{ number_format($rate->{$rateField}, 2) }}
                                    </td>
                                    @if ($showBothRates)
                                        <td class="px-4 py-4 text-right font-heading text-muted">{{ number_format($rate->buy_rate, 2) }}</td>
                                        <td class="px-4 py-4 text-right font-heading text-muted">{{ number_format($rate->sell_rate, 2) }}</td>
                                    @endif
                                    <td class="bg-placeholder/25 px-6 py-4 text-right font-heading text-base font-bold whitespace-nowrap text-ink">
                                        {{ $amd($total) }}
                                        <span class="text-xs font-normal text-muted">{{ __('exchange_quotes.request.amd') }}</span>
                                    </td>
                                @else
                                    <td class="px-6 py-4 text-right font-heading text-base text-ink">
                                        <span class="inline-flex items-center justify-end gap-2">
                                            @if ($isBestRate((float) $rate->buy_rate, $bestBuy))
                                                <x-rates.best-chip />
                                            @endif
                                            {{ number_format($rate->buy_rate, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-heading text-base text-ink">
                                        <span class="inline-flex items-center justify-end gap-2">
                                            @if ($isBestRate((float) $rate->sell_rate, $bestSell))
                                                <x-rates.best-chip />
                                            @endif
                                            {{ number_format($rate->sell_rate, 2) }}
                                        </span>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            {{-- Never a dead end: offer the nearest combination that has data
            rather than only reporting the absence. --}}
            <div class="mt-8 rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
                <p class="text-sm text-muted">{{ __('rates.no_rates_match') }}</p>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    @if ($suggestedType)
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

    </section>

    {{-- Outside the panel: it is morphed on every filter click, and a dialog
    patched underneath an open form would lose what was typed into it. --}}
    <x-rate-alert-modal
        :currencies="$currencies"
        :organizations="$alertOrganizations"
        :rate-types="$alertRateTypes"
    />
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
