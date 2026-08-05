@extends('layouts.app')

@section('title', __('rates.all_heading') . ' — Findex')

@php
    // Carries the current filter state onto every link/form on this page so
    // changing one filter never silently resets the others.
    $baseParams = [
        'type' => $selectedType->value,
        'org_type' => $selectedOrgType,
        'organization' => $selectedOrganization?->slug,
        'city' => $selectedCity,
        'sort' => $sort,
        'direction' => $direction,
        'lat' => $latitude,
        'lng' => $longitude,
    ];

    $hasNonDefaultFilter = $selectedType !== \App\Enums\RateType::CASH || $selectedOrgType || $selectedOrganization || $selectedCity || $hasLocation;
@endphp

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        {{-- One alert entry point for the whole table (not one per row), following the currently-selected currency - same idea as rates-table.blade.php's header CTA. --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('rates.all_heading') }}</h1>
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

        {{-- Large-amount exchange quote CTA - pre-fills the currency
        currently being viewed on the request form. --}}
        <a
            href="{{ route('exchange.request', array_filter(['currency' => $selectedCurrency?->code])) }}"
            class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary/30 bg-primary/5 px-5 py-4 transition hover:border-primary/50"
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

        {{-- Currency --}}
        <div class="mt-8 flex gap-1 overflow-x-auto border-b border-placeholder">
            @foreach ($currencies as $currency)
                <a
                    href="{{ route('rates.index', array_filter([...$baseParams, 'currency' => $currency->code])) }}"
                    class="shrink-0 px-4 py-3 text-xs font-semibold tracking-wide whitespace-nowrap uppercase transition {{ $selectedCurrency?->id === $currency->id ? 'bg-primary text-white' : 'text-muted hover:text-ink' }}"
                >
                    {{ $currency->code }}
                </a>
            @endforeach
        </div>

        {{-- Rate type, bank, city --}}
        <div class="mt-4 flex flex-wrap items-center gap-2">
            @foreach ($rateTypes as $rateType)
                <a
                    href="{{ route('rates.index', array_filter([...$baseParams, 'currency' => $selectedCurrency?->code, 'type' => $rateType->value])) }}"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $selectedType === $rateType ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                >
                    {{ __('organizations.rate_types.' . $rateType->value) }}
                </a>
            @endforeach

            @if ($orgTypes->count() > 1)
                <span class="mx-1 h-4 w-px bg-placeholder"></span>

                @foreach ($orgTypes as $orgType)
                    <a
                        href="{{ route('rates.index', array_filter([...$baseParams, 'currency' => $selectedCurrency?->code, 'org_type' => $orgType, 'organization' => null])) }}"
                        class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $selectedOrgType === $orgType ? 'bg-primary text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                    >
                        {{ __('organizations.types.' . $orgType) }}
                    </a>
                @endforeach
            @endif

            <form method="GET" action="{{ route('rates.index') }}" class="contents">
                <input type="hidden" name="currency" value="{{ $selectedCurrency?->code }}">
                <input type="hidden" name="type" value="{{ $selectedType->value }}">
                <input type="hidden" name="org_type" value="{{ $selectedOrgType }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">

                <select
                    name="organization"
                    onchange="this.form.submit()"
                    class="ml-2 rounded-full border border-placeholder bg-white px-3 py-1.5 text-xs font-medium text-ink focus:border-primary focus:outline-none"
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
                Client-side only - the server has no way to know the
                visitor's coordinates until the browser's own geolocation
                prompt hands them over, so this can't be a plain link like
                the filters above. Once granted, redirects to the current
                URL with lat/lng merged in (every other active filter stays
                intact, since they're already in the query string).
            --}}
            <div x-data="{
                state: 'idle',
                findNearby() {
                    if (!navigator.geolocation) {
                        this.state = 'error';
                        return;
                    }
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
                        href="{{ route('rates.index', array_filter([...$baseParams, 'currency' => $selectedCurrency?->code, 'lat' => null, 'lng' => null, 'sort' => null, 'direction' => null])) }}"
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

        <p class="mt-4 text-xs text-subtle">
            {{ trans_choice('rates.results_count', $rates->total(), ['count' => $rates->total()]) }}
        </p>

        @php
            // Clicking a sortable column heads to ascending first, then flips
            // on every subsequent click of the same column.
            $sortLink = fn (string $column) => route('rates.index', array_filter([
                ...$baseParams,
                'currency' => $selectedCurrency?->code,
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]));
            $sortArrow = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '▲' : '▼') : '';
        @endphp

        {{-- Table --}}
        <div class="mt-4 overflow-x-auto border border-placeholder">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-placeholder bg-placeholder/20 text-xs font-semibold text-subtle uppercase">
                        <th class="px-3 py-3 text-left sm:px-6">{{ __('rates.filter_bank') }}</th>
                        <th class="px-2 py-3 text-right sm:px-4">
                            <a href="{{ $sortLink('buy_rate') }}" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('organizations.buy') }} {{ $sortArrow('buy_rate') }}
                            </a>
                        </th>
                        <th class="px-2 py-3 text-right sm:px-4">
                            <a href="{{ $sortLink('sell_rate') }}" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('organizations.sell') }} {{ $sortArrow('sell_rate') }}
                            </a>
                        </th>
                        <th class="hidden px-4 py-3 text-right md:table-cell">
                            <a href="{{ $sortLink('spread') }}" class="inline-flex items-center gap-1 hover:text-ink" title="{{ __('rates.spread_hint') }}">
                                {{ __('rates.spread_column') }} {{ $sortArrow('spread') }}
                            </a>
                        </th>
                        <th class="hidden px-4 py-3 text-left sm:table-cell">{{ __('rates.updated_column') }}</th>
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
                    @forelse ($rates as $rate)
                        <tr class="border-b border-placeholder last:border-b-0">
                            <td class="max-w-[104px] px-3 py-4 sm:max-w-none sm:px-6">
                                <a href="{{ $rate->organization_url }}" class="flex items-center gap-2 sm:gap-3">
                                    @if ($rate->organization_logo)
                                        <img src="{{ $rate->organization_logo }}" alt="" class="h-7 w-7 shrink-0 rounded-full object-contain sm:h-8 sm:w-8">
                                    @else
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary sm:h-8 sm:w-8">
                                            {{ Str::of($rate->organization_name)->substr(0, 1)->upper() }}
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <span class="block truncate font-medium text-ink hover:text-primary">{{ $rate->organization_name }}</span>
                                        @if ($rate->organization_reviews_count > 0)
                                            <span class="flex items-center gap-1">
                                                <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                                <span class="text-xs text-subtle">({{ $rate->organization_reviews_count }})</span>
                                            </span>
                                        @endif
                                    </div>
                                </a>
                            </td>
                            <td class="px-2 py-4 text-right font-heading font-bold text-primary sm:px-4">{{ number_format($rate->buy_rate, 2) }}</td>
                            <td class="px-2 py-4 text-right font-heading font-bold text-[#c25b6e] sm:px-4">{{ number_format($rate->sell_rate, 2) }}</td>
                            <td class="hidden px-4 py-4 text-right text-xs text-subtle md:table-cell">
                                {{ number_format($rate->spread, 2) }}
                            </td>
                            <td class="hidden px-4 py-4 text-left text-xs text-subtle sm:table-cell">
                                {{ $rate->scraped_at ? \Illuminate\Support\Carbon::parse($rate->scraped_at)->diffForHumans() : '—' }}
                            </td>
                            @if ($hasLocation)
                                <td class="hidden px-4 py-4 text-right text-xs text-subtle lg:table-cell">
                                    {{ isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : '—' }}
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $hasLocation ? 6 : 5 }}" class="px-6 py-16 text-center text-sm text-muted">
                                {{ __('rates.no_rates_match') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rates->hasPages())
            <div class="mt-6">
                {{ $rates->links() }}
            </div>
        @endif
    </section>
@endsection
