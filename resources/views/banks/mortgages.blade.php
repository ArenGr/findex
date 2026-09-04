@extends('layouts.app')

@section('title', __('offers.categories.mortgages.title') . ' — Findex')

@section('content')
    <x-page-hero
        :title="__('offers.categories.mortgages.title')"
        :subtitle="__('offers.categories.mortgages.body')"
    >
        <x-slot:eyebrow>
            <a href="{{ route('banks.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary transition hover:bg-primary/15">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                {{ __('offers.back_to_all') }}
            </a>
        </x-slot:eyebrow>
        <x-slot:illustration><x-hero-art.banking /></x-slot:illustration>
    </x-page-hero>

    <section class="site-container pt-10 pb-16">

        {{-- Tier 1 - market context. A headline benchmark and a
             product-by-product overview, so a single bank's rate can be read
             as cheap or dear rather than in a vacuum (the NerdWallet pattern,
             adapted: our benchmark averages the rates WE collect, and is
             labelled as sample data, not a survey index). --}}
        @isset($mortgageBenchmark)
            <div class="mt-8 grid gap-4 lg:grid-cols-[minmax(0,320px)_1fr]">
                <div class="rounded-2xl border border-placeholder bg-primary/5 p-5">
                    <p class="text-xs font-medium tracking-wider text-subtle uppercase">{{ __('offers.mortgage_market.benchmark_label') }}</p>
                    @if ($mortgageBenchmark['avg_rate'] !== null)
                        <p class="mt-1 font-heading text-3xl font-bold text-ink">{{ number_format($mortgageBenchmark['avg_rate'], 2) }}%</p>
                        <p class="mt-1 text-sm text-muted">
                            {{ __('offers.mortgage_market.benchmark_from', ['rate' => number_format($mortgageBenchmark['min_rate'], 2)]) }}
                            · {{ __('offers.mortgage_market.benchmark_count', ['count' => $mortgageBenchmark['count']]) }}
                        </p>
                        @if ($mortgageBenchmark['as_of'])
                            <p class="mt-3 text-xs text-muted">{{ __('offers.mortgage_market.as_of', ['date' => $mortgageBenchmark['as_of']->translatedFormat('D MMMM YYYY')]) }}</p>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-muted">{{ __('offers.mortgage_ranking.empty') }}</p>
                    @endif
                </div>

                @if (! empty($mortgageOverview))
                    <div class="overflow-hidden rounded-2xl border border-placeholder">
                        <div class="border-b border-placeholder px-5 py-3">
                            <h2 class="font-heading text-base font-semibold text-ink">{{ __('offers.mortgage_market.heading') }}</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-placeholder text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                        <th class="px-5 py-2.5">{{ __('offers.mortgage_market.col_product') }}</th>
                                        <th class="px-5 py-2.5">{{ __('offers.mortgage_market.col_avg') }}</th>
                                        <th class="px-5 py-2.5">{{ __('offers.mortgage_market.col_from') }}</th>
                                        <th class="px-5 py-2.5">{{ __('offers.mortgage_market.col_banks') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($mortgageOverview as $row)
                                        <tr class="border-b border-placeholder/60 last:border-0">
                                            <td class="px-5 py-3 text-ink">{{ $row['currency'] }} · {{ __('offers.mortgage_categories.'.$row['category']) }}</td>
                                            <td class="px-5 py-3 font-semibold text-ink tabular-nums">{{ number_format($row['avg_rate'], 2) }}%</td>
                                            <td class="px-5 py-3 tabular-nums text-muted">{{ number_format($row['min_rate'], 2) }}%</td>
                                            <td class="px-5 py-3 tabular-nums text-muted">{{ $row['count'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <p class="mt-3 max-w-3xl text-xs text-muted">{{ __('offers.mortgage_market.source_note') }}</p>
        @endisset

        {{-- Transparency: define APR right next to the numbers, not buried in
             an FAQ - the difference between headline and APR is the whole
             point of ranking on APR. --}}
        <div class="mt-6 rounded-2xl border border-placeholder bg-placeholder/10 p-5">
            <h2 class="font-heading text-sm font-semibold text-ink">{{ __('offers.mortgage_market.apr_heading') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-muted">{{ __('offers.mortgage_market.apr_body') }}</p>
        </div>

        <div class="mt-8 overflow-hidden rounded-2xl border border-placeholder">
            <x-mortgage-offers-table />
        </div>
    </section>
@endsection
