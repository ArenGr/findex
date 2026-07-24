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
    ];

    $hasNonDefaultFilter = $selectedType !== \App\Enums\RateType::CASH || $selectedOrgType || $selectedOrganization || $selectedCity;
@endphp

@section('content')
    <section class="mx-auto max-w-6xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('rates.all_heading') }}</h1>
        <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('rates.all_subheading') }}</p>

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
                        <th class="px-6 py-3 text-left">{{ __('rates.filter_bank') }}</th>
                        <th class="hidden px-4 py-3 text-right sm:table-cell">
                            <a href="{{ $sortLink('buy_rate') }}" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('organizations.buy') }} {{ $sortArrow('buy_rate') }}
                            </a>
                        </th>
                        <th class="px-4 py-3 text-right">
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
                        <th class="px-4 py-3 text-right">{{ __('rates.alert_column') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rates as $rate)
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
                                        <span class="block font-medium text-ink hover:text-primary">{{ $rate->organization_name }}</span>
                                        @if ($rate->organization_reviews_count > 0)
                                            <span class="flex items-center gap-1">
                                                <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                                <span class="text-xs text-subtle">({{ $rate->organization_reviews_count }})</span>
                                            </span>
                                        @endif
                                    </div>
                                </a>
                            </td>
                            <td class="hidden px-4 py-4 text-right font-heading font-bold text-primary sm:table-cell">{{ number_format($rate->buy_rate, 2) }}</td>
                            <td class="px-4 py-4 text-right font-heading font-bold text-[#c25b6e]">{{ number_format($rate->sell_rate, 2) }}</td>
                            <td class="hidden px-4 py-4 text-right text-xs text-subtle md:table-cell">
                                {{ number_format($rate->spread, 2) }}
                            </td>
                            <td class="hidden px-4 py-4 text-left text-xs text-subtle sm:table-cell">
                                {{ $rate->scraped_at ? \Illuminate\Support\Carbon::parse($rate->scraped_at)->diffForHumans() : '—' }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a
                                    href="{{ route('alerts.index', ['currency_id' => $selectedCurrency?->id, 'organization_id' => $rate->organization_id, 'rate_type' => $selectedType->value, 'rate_field' => 'sell_rate']) }}#create-alert"
                                    title="{{ __('rates.create_alert') }}"
                                    class="inline-flex text-subtle hover:text-primary"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                        <path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-sm text-muted">
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
