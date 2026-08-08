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

    $rowCount = collect($groups)->sum('count');
    $allStale = $rowCount > 0 && collect($groups)
        ->flatMap(fn (array $group) => $group['rows'])
        ->every(fn ($row) => $isStale($row->scraped_at));

    $activeFilterCount = collect([$selectedOrganization, $selectedCity, $hasLocation ?: null])
        ->filter()->count();
@endphp

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold break-words text-ink lg:text-3xl">{{ __('rates.all_heading') }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('rates.all_subheading') }}</p>
            </div>

            <a
                href="{{ route('alerts.index', array_filter(['currency_id' => $selectedCurrency?->id])) }}#create-alert"
                class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-ink hover:text-primary"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 shrink-0 text-[#D4A72C]">
                    <path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" clip-rule="evenodd" />
                </svg>
                {{ __('rates.alert_cta') }}
            </a>
        </div>

        {{-- Currency: the primary axis, so it stays a scannable tab strip
        rather than another dropdown. --}}
        <div class="mt-8 flex gap-1 overflow-x-auto border-b border-placeholder [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($currencies as $currency)
                <a
                    href="{{ $link(['currency' => $currency->code]) }}"
                    class="shrink-0 px-4 py-3 text-xs font-semibold tracking-wide whitespace-nowrap uppercase transition {{ $selectedCurrency?->id === $currency->id ? 'bg-primary text-white' : 'text-muted hover:text-ink' }}"
                >
                    {{ $currency->code }}
                </a>
            @endforeach
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

            <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                <div>
                    <span class="block text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('rates.intent_label') }}</span>
                    <div class="mt-1.5 inline-flex rounded-full border border-border-muted bg-white p-0.5">
                        @foreach (['buy', 'sell'] as $option)
                            <label class="cursor-pointer">
                                <input type="radio" name="intent" value="{{ $option }}" class="peer sr-only" @checked($intent === $option)>
                                <span class="block rounded-full px-4 py-1.5 text-sm font-medium text-muted transition peer-checked:bg-primary peer-checked:text-white">
                                    {{ __('rates.intent_'.$option, ['currency' => $selectedCurrency?->code]) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="amount" class="block text-xs font-semibold tracking-wider text-subtle uppercase">
                        {{ __('rates.amount_label') }}
                    </label>
                    <div class="mt-1.5 flex items-center gap-2">
                        <input
                            type="number" inputmode="decimal" step="0.01" min="0"
                            name="amount" id="amount"
                            value="{{ $amount }}"
                            placeholder="{{ __('rates.amount_placeholder') }}"
                            class="w-32 rounded-md border border-border-muted bg-white px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        >
                        <span class="text-sm font-semibold text-ink">{{ $selectedCurrency?->code }}</span>
                    </div>
                </div>

                <button type="submit" class="rounded-md bg-primary px-5 py-2 text-sm font-medium text-white transition hover:bg-primary-dark">
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
            <div class="mt-6 flex flex-wrap gap-2">
                <a
                    href="{{ $link(['org_type' => null, 'organization' => null]) }}"
                    class="rounded-full px-4 py-2 text-sm font-medium transition {{ $selectedOrgType === null ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                >
                    {{ __('rates.market_all') }}
                </a>
                @foreach ($orgTypes as $orgType)
                    <a
                        href="{{ $link(['org_type' => $orgType, 'organization' => null]) }}"
                        class="rounded-full px-4 py-2 text-sm font-medium transition {{ $selectedOrgType === $orgType ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                    >
                        {{ __('rates.markets.' . $orgType) }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Transaction type: only the ones this currency actually has, so a
        pill never leads to an empty table. --}}
        <div class="mt-5">
            <span class="block text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('rates.type_label') }}</span>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($availableTypes as $typeValue)
                    <a
                        href="{{ $link(['type' => $typeValue]) }}"
                        class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $selectedType->value === $typeValue ? 'bg-primary text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                    >
                        {{ __('organizations.rate_types.' . $typeValue) }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Secondary filters, collapsed behind a toggle on mobile so they
        don't push the results themselves below the fold. --}}
        <div x-data="{ open: false }" class="mt-5">
            <button
                type="button"
                @click="open = !open"
                class="flex items-center gap-2 text-xs font-semibold tracking-wider text-subtle uppercase sm:hidden"
                :aria-expanded="open"
            >
                {{ __('rates.more_filters') }}@if ($activeFilterCount) ({{ $activeFilterCount }}) @endif
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                    <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div x-show="open" x-cloak class="mt-3 flex flex-wrap items-center gap-2 sm:!flex sm:mt-0">
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

                    <select
                        name="organization"
                        onchange="this.form.submit()"
                        class="rounded-full border border-placeholder bg-white px-3 py-1.5 text-xs font-medium text-ink focus:border-primary focus:outline-none"
                    >
                        <option value="">{{ __('rates.filter_bank_all') }}</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization->slug }}" @selected($selectedOrganization?->id === $organization->id)>
                                {{ $organization->name }}
                            </option>
                        @endforeach
                    </select>

                    @if ($cities->isNotEmpty())
                        <select
                            name="city"
                            onchange="this.form.submit()"
                            title="{{ __('rates.filter_city_hint') }}"
                            class="rounded-full border border-placeholder bg-white px-3 py-1.5 text-xs font-medium text-ink focus:border-primary focus:outline-none"
                        >
                            <option value="">{{ __('rates.filter_city_all') }}</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" @selected($selectedCity === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
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
                                window.location.href = url.toString();
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
                            class="inline-flex items-center gap-1 rounded-full border border-placeholder bg-white px-3 py-1.5 text-xs font-medium text-ink hover:border-primary disabled:opacity-60"
                        >
                            <span x-show="state !== 'locating'">📍 {{ __('rates.find_nearby') }}</span>
                            <span x-show="state === 'locating'" x-cloak>{{ __('rates.locating') }}</span>
                        </button>
                        <p x-show="state === 'error'" x-cloak class="mt-1 text-xs text-red-600">{{ __('rates.location_error') }}</p>
                    @endif
                </div>

                @if ($hasNonDefaultFilter)
                    <a href="{{ route('rates.index', array_filter(['currency' => $selectedCurrency?->code])) }}" class="text-xs text-muted hover:text-ink">
                        {{ __('rates.reset_filters') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-1">
            <p class="text-xs text-subtle">
                {{ trans_choice('rates.results_count', $rowCount, ['count' => $rowCount]) }}
            </p>
            @if ($allStale)
                <p class="text-xs text-[#B4791F]">{{ __('rates.all_stale_notice') }}</p>
            @endif
        </div>

        @php
            // Ascending first, flipping on a repeat click of the same column.
            $sortLink = fn (string $column) => $link([
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]);
            $sortArrow = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '▲' : '▼') : '';
        @endphp

        @forelse ($groups as $group)
            @php
                $bestShown = false;
                $marketSaving = $amount !== null && $group['spread_across_market'] !== null
                    ? $amount * $group['spread_across_market']
                    : null;
            @endphp

            <div class="mt-8">
                {{-- Only worth naming the market when both are on screen. --}}
                @if (count($groups) > 1)
                    <div class="flex flex-wrap items-baseline gap-x-3">
                        <h2 class="font-heading text-sm font-semibold tracking-wide text-ink uppercase">
                            {{ __('rates.markets.' . $group['type']) }}
                        </h2>
                        <span class="text-xs text-subtle">{{ $group['count'] }}</span>
                    </div>
                @endif

                @if ($marketSaving !== null && $marketSaving >= 1)
                    <p class="mt-1 text-xs text-muted">
                        {{ __('rates.market_saving', [
                            'amount' => number_format($marketSaving),
                            'currency' => __('exchange_quotes.request.amd'),
                        ]) }}
                    </p>
                @endif

                {{-- Mobile: a row list. A table has no room for a readable name
                once both rate columns need space. --}}
                <div class="mt-3 border border-placeholder sm:hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-placeholder bg-placeholder/20 px-4 py-2 text-xs font-semibold text-subtle uppercase">
                        <span>{{ __('rates.filter_bank') }}</span>
                        <span>{{ $isBuying ? __('rates.you_buy_at') : __('rates.you_sell_at') }}</span>
                    </div>

                    <div class="divide-y divide-placeholder">
                        @foreach ($group['rows'] as $rate)
                            @php
                                $isBest = ! $bestShown && (float) $rate->{$rateField} === (float) $group['best_value'];
                                $bestShown = $bestShown || $isBest;
                                $total = $amount !== null ? $amount * (float) $rate->{$rateField} : null;
                            @endphp
                            <a href="{{ $rate->organization_url }}" class="flex items-center gap-3 px-4 py-4 {{ $isBest ? 'bg-primary/5' : '' }}">
                                @if ($rate->organization_logo)
                                    <img src="{{ $rate->organization_logo }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-contain">
                                @else
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                        {{ Str::of($rate->organization_name)->substr(0, 1)->upper() }}
                                    </span>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-ink">{{ $rate->organization_name }}</p>
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        @if ($isBest)
                                            <span class="rounded-full bg-primary px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-white uppercase">
                                                {{ __('rates.best_badge') }}
                                            </span>
                                        @endif
                                        @if ($hasLocation && isset($rate->distance_km))
                                            <span class="text-xs text-subtle">{{ __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) }}</span>
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
                                        <p class="text-xs text-subtle">
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
                            <tr class="border-b border-placeholder bg-placeholder/20 text-xs font-semibold text-subtle uppercase">
                                <th class="px-6 py-3 text-left">{{ __('rates.filter_bank') }}</th>
                                <th class="px-4 py-3 text-right">
                                    <a href="{{ $sortLink('buy_rate') }}" class="inline-flex items-center gap-1 hover:text-ink" title="{{ __('rates.you_sell_at_hint') }}">
                                        {{ __('rates.you_sell_at') }} {{ $sortArrow('buy_rate') }}
                                    </a>
                                </th>
                                <th class="px-4 py-3 text-right">
                                    <a href="{{ $sortLink('sell_rate') }}" class="inline-flex items-center gap-1 hover:text-ink" title="{{ __('rates.you_buy_at_hint') }}">
                                        {{ __('rates.you_buy_at') }} {{ $sortArrow('sell_rate') }}
                                    </a>
                                </th>
                                @if ($amount !== null)
                                    <th class="px-4 py-3 text-right">
                                        {{ $isBuying ? __('rates.you_pay_column') : __('rates.you_get_column') }}
                                    </th>
                                @endif
                                <th class="hidden px-4 py-3 text-right md:table-cell">
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
                            @foreach ($group['rows'] as $rate)
                                @php
                                    $isBest = ! $bestShown && (float) $rate->{$rateField} === (float) $group['best_value'];
                                    $bestShown = $bestShown || $isBest;
                                    $total = $amount !== null ? $amount * (float) $rate->{$rateField} : null;
                                    $stale = $isStale($rate->scraped_at);
                                @endphp
                                <tr class="border-b border-placeholder last:border-b-0 {{ $isBest ? 'bg-primary/5' : '' }}">
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
                                                        <span class="shrink-0 rounded-full bg-primary px-2 py-0.5 text-[9px] font-semibold tracking-wide text-white uppercase">
                                                            {{ __('rates.best_badge') }}
                                                        </span>
                                                    @endif
                                                </span>
                                                @if ($rate->organization_reviews_count > 0)
                                                    <span class="flex items-center gap-1">
                                                        <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                                        <span class="text-xs text-subtle">({{ $rate->organization_reviews_count }})</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 text-right font-heading font-bold {{ $isBuying ? 'text-subtle' : 'text-primary' }}">
                                        {{ number_format($rate->buy_rate, 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-heading font-bold {{ $isBuying ? 'text-[#c25b6e]' : 'text-subtle' }}">
                                        {{ number_format($rate->sell_rate, 2) }}
                                    </td>
                                    @if ($total !== null)
                                        <td class="px-4 py-4 text-right font-heading font-bold whitespace-nowrap text-ink">
                                            {{ number_format($total) }}
                                            <span class="text-xs font-normal text-subtle">{{ __('exchange_quotes.request.amd') }}</span>
                                        </td>
                                    @endif
                                    <td class="hidden px-4 py-4 text-right text-xs text-subtle md:table-cell">
                                        {{ number_format($rate->spread, 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-left text-xs {{ $stale ? 'text-[#B4791F]' : 'text-subtle' }}">
                                        {{ $rate->scraped_at ? Carbon::parse($rate->scraped_at)->diffForHumans() : '—' }}
                                    </td>
                                    @if ($hasLocation)
                                        <td class="hidden px-4 py-4 text-right text-xs text-subtle lg:table-cell">
                                            {{ isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : '—' }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
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
        @endforelse

        {{-- Large-amount negotiation: most relevant once someone has entered a
        real amount, so it sits after the results rather than above them. --}}
        <a
            href="{{ route('exchange.request', array_filter(['currency' => $selectedCurrency?->code])) }}"
            class="mt-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary/30 bg-primary/5 px-5 py-4 transition hover:border-primary/50"
        >
            <span class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl">💱</span>
                <span>
                    <span class="block text-sm font-semibold text-ink">{{ __('exchange_quotes.request.badge') }}</span>
                    <span class="block text-xs text-muted">{{ __('exchange_quotes.request.subheading') }}</span>
                </span>
            </span>
            <span class="shrink-0 text-sm font-medium text-primary">{{ __('exchange_quotes.request.submit') }} &rarr;</span>
        </a>
    </section>
@endsection
