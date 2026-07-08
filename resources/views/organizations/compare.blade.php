@extends('layouts.app')

@section('title', __('organizations.compare_heading') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('organizations.compare_heading') }}</h1>
        <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('organizations.compare_subtitle') }}</p>

        @if ($organizations->count() < 2)
            <p class="mt-10 border border-placeholder p-6 text-sm text-muted">
                {{ __('organizations.compare_need_more_full') }}
                <a href="{{ route('organizations.index') }}" class="font-medium text-primary hover:underline">{{ __('organizations.view_all') }}</a>
            </p>
        @else
            <div class="mt-10 overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="w-40"></th>
                            @foreach ($organizations as $organization)
                                <th class="border-b border-placeholder px-4 py-4 text-left align-bottom">
                                    <a href="{{ route('organizations.show', $organization) }}" class="flex items-center gap-3">
                                        @if ($organization->logo)
                                            <img src="{{ $organization->logo }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-contain">
                                        @else
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                                                {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                                            </div>
                                        @endif
                                        <span class="font-semibold text-ink hover:text-primary">{{ $organization->name }}</span>
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.type') }}</th>
                            @foreach ($organizations as $organization)
                                <td class="border-t border-placeholder px-4 py-4 text-ink">{{ __('organizations.types.' . $organization->type) }}</td>
                            @endforeach
                        </tr>

                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.reviews_heading') }}</th>
                            @foreach ($organizations as $organization)
                                <td class="border-t border-placeholder px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <x-star-rating :rating="$organization->reviews_avg_rating ?? 0" />
                                        <span class="text-xs text-muted whitespace-nowrap">
                                            @if ($organization->reviews_count > 0)
                                                {{ number_format($organization->reviews_avg_rating, 1) }}
                                                ({{ trans_choice('organizations.reviews_count', $organization->reviews_count, ['count' => $organization->reviews_count]) }})
                                            @else
                                                {{ __('organizations.unrated') }}
                                            @endif
                                        </span>
                                    </div>
                                </td>
                            @endforeach
                        </tr>

                        @foreach ($currencies as $currencyCode)
                            <tr>
                                <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                    {{ __('organizations.cash_rate', ['currency' => $currencyCode]) }}
                                </th>
                                @foreach ($organizations as $organization)
                                    @php($rate = $ratesByOrgId->get($organization->id)?->firstWhere('currency.code', $currencyCode))
                                    <td class="border-t border-placeholder px-4 py-4 text-ink">
                                        @if ($rate)
                                            <span class="font-heading font-bold text-primary">{{ number_format($rate->buy_rate, 2) }}</span>
                                            / <span class="font-heading font-bold text-[#c25b6e]">{{ number_format($rate->sell_rate, 2) }}</span>
                                        @else
                                            <span class="text-subtle">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.compare_mortgage_rate') }}</th>
                            @foreach ($organizations as $organization)
                                @php($offers = $mortgagesByOrgId->get($organization->id))
                                <td class="border-t border-placeholder px-4 py-4 text-ink">
                                    @if ($offers && $offers->isNotEmpty())
                                        {{ number_format($offers->min('interest_rate_min'), 2) }}% – {{ number_format($offers->max('interest_rate_max'), 2) }}%
                                    @else
                                        <span class="text-subtle">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>

                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.website') }}</th>
                            @foreach ($organizations as $organization)
                                <td class="border-t border-placeholder px-4 py-4">
                                    <a href="{{ $organization->website }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">
                                        {{ __('organizations.compare_visit') }}
                                    </a>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
