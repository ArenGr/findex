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
    ];

    $link = fn (array $overrides = []) => route('rates.index', array_filter(
        [...$baseParams, 'currency' => $selectedCurrency?->code, ...$overrides],
        fn ($value) => $value !== null && $value !== '',
    ));

    $hasNonDefaultFilter = $selectedType !== \App\Enums\RateType::CASH
        || $selectedOrgType || $selectedOrganization || $selectedCity || $hasLocation || $amount !== null;

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
    $marketSaving = $amount !== null && $ranked['spread'] !== null ? $amount * $ranked['spread'] : null;

    // Banks and exchange offices share one table, so each row says which it is
    // - but only when the list actually mixes them.
    $showMarket = collect($ranked['rows'])->pluck('organization_type')->unique()->count() > 1;

    $marketStyle = fn (?string $type) => match ($type) {
        'bank' => 'border-market-bank-line bg-market-bank-tint text-market-bank-ink',
        'exchange' => 'border-market-exchange-line bg-market-exchange-tint text-market-exchange-ink',
        default => 'border-border-muted text-muted',
    };


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
                @php $qualifies = $amount !== null && $amount >= $quoteMinimum; @endphp
                <div class="flex items-center gap-2 sm:shrink-0">
                    <a
                        href="{{ route('exchange.request', array_filter(['currency' => $selectedCurrency?->code, 'amount' => $amount])) }}"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-ink hover:text-primary"
                    >
                        {{-- A handshake, not exchange arrows: the arrows read as
                        "swap currency", which is what the whole page already does. --}}
                        <svg
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                            stroke-linecap="round" stroke-linejoin="round"
                            class="h-5 w-5 shrink-0 text-accent-yellow" aria-hidden="true"
                        >
                            <path d="m11 17 2 2a1 1 0 1 0 3-3" />
                            <path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4" />
                            <path d="m21 3 1 11h-2" />
                            <path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3" />
                            <path d="M3 4h8" />
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

        {{-- Currency: the primary axis, so it stays a scannable tab strip
        rather than another dropdown. --}}
        <div class="mt-8">
            <span class="{{ $labelClass }}">{{ __('rates.currency_label') }}</span>
            <div class="mt-2 flex gap-1 overflow-x-auto border-b border-placeholder [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($currencies as $currency)
                    <a
                        href="{{ $link(['currency' => $currency->code]) }}"
                        class="flex shrink-0 items-center gap-1.5 px-4 py-3 text-sm font-semibold tracking-wide whitespace-nowrap uppercase transition {{ $selectedCurrency?->id === $currency->id ? 'bg-border-muted text-ink' : 'text-muted hover:text-ink' }}"
                    >
                        <span aria-hidden="true" class="text-base">{{ \App\Models\Currency::flag($currency->code) }}</span>
                        {{ $currency->code }}
                    </a>
                @endforeach
            </div>
        </div>

        {{--
            Intent bar. Buy/sell decides the ranking, so the visitor never has
            to work out which institution-side column to sort by; the amount is
            optional and only enriches the display. Both live in the query
            string so a result stays shareable.
        --}}
        <form method="GET" action="{{ route('rates.index') }}" class="mt-6 rounded-2xl border border-primary/30 bg-primary/5 px-5 py-4">
            @foreach (['type', 'org_type', 'organization', 'city', 'lat', 'lng'] as $carried)
                @if (!empty($baseParams[$carried]))
                    <input type="hidden" name="{{ $carried }}" value="{{ $baseParams[$carried] }}">
                @endif
            @endforeach
            <input type="hidden" name="currency" value="{{ $selectedCurrency?->code }}">

            {{-- Alpine-only, deliberately: with JS off the buy/sell radios do
            not self-submit, so this button is the only way to apply an intent
            change and must stay enabled. --}}
            <div x-data="{ amount: @js($amount) }" class="flex flex-wrap items-end gap-x-4 gap-y-3">
                <div>
                    <span class="{{ $labelClass }}">{{ __('rates.intent_label') }}</span>
                    <div class="mt-1.5 inline-flex rounded-full border border-border-muted bg-white p-0.5">
                        @foreach (['buy', 'sell'] as $option)
                            <label class="cursor-pointer">
                                {{-- Submits on pick so switching buy/sell is one click; the button
                                below stays for the amount field and for no-JS. --}}
                                <input
                                    type="radio" name="intent" value="{{ $option }}"
                                    class="peer sr-only" @checked($intent === $option)
                                    onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                >
                                <span class="block rounded-full px-4 py-1.5 text-sm font-medium text-muted transition peer-checked:bg-border-muted peer-checked:text-ink">
                                    {{ __('rates.intent_'.$option, ['currency' => $selectedCurrency?->code]) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="amount" class="{{ $labelClass }}">
                        {{ __('rates.amount_label') }}
                    </label>
                    <div class="mt-1.5 flex items-center gap-2">
                        <input
                            type="number" inputmode="decimal" step="0.01" min="0"
                            name="amount" id="amount"
                            x-model="amount"
                            value="{{ $amount }}"
                            placeholder="{{ __('rates.amount_placeholder') }}"
                            class="w-32 rounded-md border border-border-muted bg-white px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        >
                        <span class="text-sm font-semibold text-ink">{{ $selectedCurrency?->code }}</span>
                    </div>
                </div>

                {{-- Number(), not a truthiness check: the string "0" is truthy
                in JS, and the controller drops a zero amount anyway. --}}
                <button
                    type="submit"
                    :disabled="!(Number(amount) > 0)"
                    class="rounded-md bg-primary px-5 py-2 text-sm font-medium text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:bg-placeholder disabled:text-muted disabled:hover:bg-placeholder"
                >
                    {{ __('rates.intent_submit') }}
                </button>

                @if ($amount !== null)
                    <a href="{{ $link(['amount' => null]) }}" class="pb-2 text-xs text-muted hover:text-ink">
                        {{ __('rates.amount_clear') }}
                    </a>
                @endif
            </div>
        </form>

        {{-- Market tabs. Banks and exchange offices quote very different
        levels, so they are separated rather than interleaved. --}}
        @if ($orgTypes->count() > 1)
            <div class="mt-6">
                <span class="{{ $labelClass }}">{{ __('rates.market_label') }}</span>
                <div class="mt-2 flex flex-wrap gap-2">
                <a
                    href="{{ $link(['org_type' => null, 'organization' => null]) }}"
                    class="rounded-full px-4 py-2 text-sm font-medium transition {{ $selectedOrgType === null ? 'bg-border-muted text-ink' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                >
                    {{ __('rates.market_all') }}
                </a>
                @foreach ($orgTypes as $orgType)
                    <a
                        href="{{ $link(['org_type' => $orgType, 'organization' => null]) }}"
                        class="rounded-full px-4 py-2 text-sm font-medium transition {{ $selectedOrgType === $orgType ? 'bg-border-muted text-ink' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                    >
                        {{ __('rates.markets.' . $orgType) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Transaction type: only the ones this currency actually has, so a
        pill never leads to an empty table. --}}
        <div class="mt-5">
            <span class="{{ $labelClass }}">{{ __('rates.type_label') }}</span>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($availableTypes as $typeValue)
                    <a
                        href="{{ $link(['type' => $typeValue]) }}"
                        class="rounded-full px-4 py-2 text-sm font-medium transition {{ $selectedType->value === $typeValue ? 'bg-border-muted text-ink' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                    >
                        {{ __('organizations.rate_types.' . $typeValue) }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Secondary filters, collapsed behind a toggle on mobile so they
        don't push the results themselves below the fold. --}}
        <div x-data="{ open: window.__ratesFiltersOpen ?? false }" x-effect="window.__ratesFiltersOpen = open" class="mt-5">
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
                    @if ($amount !== null)<input type="hidden" name="amount" value="{{ $amount }}">@endif
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

                @if ($hasNonDefaultFilter)
                    {{-- Bottom-aligned with the pills, so the padding optically
                    centres this bare text against them. --}}
                    <a href="{{ route('rates.index', array_filter(['currency' => $selectedCurrency?->code])) }}" class="pb-2 text-sm text-muted hover:text-ink">
                        {{ __('rates.reset_filters') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2">
            <p class="text-sm text-muted">
                {{ trans_choice('rates.results_count', $rowCount, ['count' => $rowCount]) }}
            </p>
            @if ($allStale)
                <p class="text-sm text-[#B4791F]">{{ __('rates.all_stale_notice') }}</p>
            @endif

            {{-- Sits with the results rather than in the page header: an alert
            is a follow-up to what you are looking at. --}}
            <div class="ms-auto flex items-center gap-2">
                <a
                    href="{{ route('alerts.index', array_filter(['currency_id' => $selectedCurrency?->id])) }}#create-alert"
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
        </div>

        @php
            // Ascending first, flipping on a repeat click of the same column.
            $sortLink = fn (string $column) => $link([
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]);
            $sortArrow = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '▲' : '▼') : '';
        @endphp

        @if ($rowCount > 0)
            <div class="mt-6">
                @if ($marketSaving !== null && $marketSaving >= 1)
                    <p class="text-sm font-medium break-words text-ink">
                        {{ __('rates.market_saving', [
                            'amount' => number_format($marketSaving),
                            'currency' => __('exchange_quotes.request.amd'),
                        ]) }}
                    </p>
                @elseif ($ranked['spread'] !== null && $ranked['spread'] >= 0.01)
                    <p class="text-sm break-words text-muted">
                        {{ __('rates.market_saving_per_unit', [
                            'amount' => number_format($ranked['spread'], 2),
                            'currency' => __('exchange_quotes.request.amd'),
                            'code' => $selectedCurrency?->code,
                        ]) }}
                    </p>
                @endif

                {{-- Mobile: a row list. A table has no room for a readable name
                once both rate columns need space. --}}
                <div class="mt-3 border border-placeholder sm:hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-placeholder bg-placeholder/20 px-4 py-2 text-xs font-semibold text-muted uppercase">
                        <span>{{ __('rates.provider_column') }}</span>
                        <span>{{ $isBuying ? __('rates.sell_column') : __('rates.buy_column') }}</span>
                    </div>

                    @php $bestShown = false; @endphp
                    <div class="divide-y divide-placeholder">
                        @foreach ($ranked['rows'] as $rate)
                            @php
                                // Ties are common - three banks quoting 368.00 all hold
                                // rank 1, and badging every one of them makes "Best" mean
                                // nothing. Only the first gets it; the equal rates sit
                                // directly beneath and read as equal.
                                $isBest = $rate->rank === 1 && ! $bestShown;
                                $bestShown = $bestShown || $isBest;
                                $total = $amount !== null ? $amount * (float) $rate->{$rateField} : null;
                            @endphp
                            <a href="{{ $rate->organization_url }}" class="flex items-center gap-3 px-4 py-4">
                                @if ($rate->organization_logo)
                                    <img src="{{ $rate->organization_logo }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-contain">
                                @else
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                        {{ Str::of($rate->organization_name)->substr(0, 1)->upper() }}
                                    </span>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <p class="font-medium break-words text-ink">{{ $rate->organization_name }}</p>
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        @if ($isBest)
                                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-accent-yellow px-2.5 py-1 text-xs font-semibold text-ink">
                                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 shrink-0" aria-hidden="true">
                                                    <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z" />
                                                </svg>
                                                {{ __('rates.best_badge') }}
                                            </span>
                                        @endif
                                        @if ($showMarket)
                                            <span class="rounded-full border px-1.5 py-0.5 text-[10px] font-medium {{ $marketStyle($rate->organization_type) }}">
                                                {{ __('rates.market_badge.' . $rate->organization_type) }}
                                            </span>
                                        @endif
                                        @if ($hasLocation && isset($rate->distance_km))
                                            <span class="text-xs text-muted">{{ __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) }}</span>
                                        @endif
                                        @if ($isStale($rate->scraped_at))
                                            <span class="text-xs text-[#B4791F]">{{ Carbon::parse($rate->scraped_at)->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <p class="font-heading font-bold {{ $isBuying ? 'text-[#c25b6e]' : 'text-primary' }}">
                                        {{ number_format($rate->{$rateField}, 2) }}
                                    </p>
                                    @if ($total !== null)
                                        <p class="text-xs text-muted">
                                            {{ __($isBuying ? 'rates.you_pay_total' : 'rates.you_get_total', [
                                                'amount' => number_format($total),
                                                'currency' => __('exchange_quotes.request.amd'),
                                            ]) }}
                                        </p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Desktop table --}}
                @php $bestShown = false; @endphp
                <div class="mt-3 hidden overflow-x-auto border border-placeholder sm:block">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-placeholder bg-placeholder/20 text-xs font-semibold text-muted uppercase">
                                <th class="px-6 py-3 text-left">{{ __('rates.provider_column') }}</th>
                                <th class="px-4 py-3 text-right">
                                    <a href="{{ $sortLink('buy_rate') }}" class="inline-flex items-center gap-1 hover:text-ink" title="{{ __('rates.buy_hint') }}">
                                        {{ __('rates.buy_column') }} {{ $sortArrow('buy_rate') }}
                                    </a>
                                </th>
                                <th class="px-4 py-3 text-right">
                                    <a href="{{ $sortLink('sell_rate') }}" class="inline-flex items-center gap-1 hover:text-ink" title="{{ __('rates.sell_hint') }}">
                                        {{ __('rates.sell_column') }} {{ $sortArrow('sell_rate') }}
                                    </a>
                                </th>
                                @if ($amount !== null)
                                    <th class="px-4 py-3 text-right">
                                        {{ $isBuying ? __('rates.you_pay_column') : __('rates.you_get_column') }}
                                    </th>
                                @endif
                                <th class="hidden px-4 py-3 text-right lg:table-cell">
                                    <a href="{{ $sortLink('spread') }}" class="inline-flex items-center gap-1 hover:text-ink" title="{{ __('rates.spread_hint') }}">
                                        {{ __('rates.spread_column') }} {{ $sortArrow('spread') }}
                                    </a>
                                </th>
                                <th class="px-4 py-3 text-left">{{ __('rates.updated_column') }}</th>
                                @if ($hasLocation)
                                    <th class="hidden px-4 py-3 text-right lg:table-cell">
                                        <a href="{{ $sortLink('distance') }}" class="inline-flex items-center gap-1 hover:text-ink">
                                            {{ __('rates.distance_column') }} {{ $sortArrow('distance') }}
                                        </a>
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ranked['rows'] as $rate)
                                @php
                                    $isBest = $rate->rank === 1 && ! $bestShown;
                                    $bestShown = $bestShown || $isBest;
                                    $total = $amount !== null ? $amount * (float) $rate->{$rateField} : null;
                                    $stale = $isStale($rate->scraped_at);
                                @endphp
                                <tr class="border-b border-placeholder last:border-b-0">
                                    <td class="px-6 py-4">
                                        <a href="{{ $rate->organization_url }}" class="flex items-center gap-3">
                                            @if ($rate->organization_logo)
                                                <img src="{{ $rate->organization_logo }}" alt="" class="h-8 w-8 shrink-0 rounded-full object-contain">
                                            @else
                                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                                    {{ Str::of($rate->organization_name)->substr(0, 1)->upper() }}
                                                </span>
                                            @endif
                                            <div class="min-w-0">
                                                <span class="flex items-center gap-2">
                                                    <span class="truncate font-medium text-ink hover:text-primary">{{ $rate->organization_name }}</span>
                                                    @if ($isBest)
                                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-accent-yellow px-2.5 py-1 text-xs font-semibold text-ink">
                                                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 shrink-0" aria-hidden="true">
                                                                <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z" />
                                                            </svg>
                                                            {{ __('rates.best_badge') }}
                                                        </span>
                                                    @endif
                                                    @if ($showMarket)
                                                        <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-medium {{ $marketStyle($rate->organization_type) }}">
                                                            {{ __('rates.market_badge.' . $rate->organization_type) }}
                                                        </span>
                                                    @endif
                                                </span>
                                                @if ($rate->organization_reviews_count > 0)
                                                    <span class="flex items-center gap-1">
                                                        <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                                        <span class="text-xs text-muted">({{ $rate->organization_reviews_count }})</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 text-right font-heading font-bold text-primary {{ $isBuying ? 'opacity-60' : 'text-base' }}">
                                        {{ number_format($rate->buy_rate, 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-heading font-bold text-[#c25b6e] {{ $isBuying ? 'text-base' : 'opacity-60' }}">
                                        {{ number_format($rate->sell_rate, 2) }}
                                    </td>
                                    @if ($total !== null)
                                        <td class="px-4 py-4 text-right font-heading font-bold whitespace-nowrap text-ink">
                                            {{ number_format($total) }}
                                            <span class="text-xs font-normal text-muted">{{ __('exchange_quotes.request.amd') }}</span>
                                        </td>
                                    @endif
                                    <td class="hidden px-4 py-4 text-right text-xs text-muted lg:table-cell">
                                        {{ number_format($rate->spread, 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-left text-xs {{ $stale ? 'text-[#B4791F]' : 'text-muted' }}">
                                        {{ $rate->scraped_at ? Carbon::parse($rate->scraped_at)->diffForHumans() : '—' }}
                                    </td>
                                    @if ($hasLocation)
                                        <td class="hidden px-4 py-4 text-right text-xs text-muted lg:table-cell">
                                            {{ isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : '—' }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
