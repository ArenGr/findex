@extends('layouts.app')

@php
    $code = $selectedCurrency?->code;
@endphp

@section('title', __('rates.history.heading', ['code' => $code]) . ' — Findex')
@section('description', __('rates.history.meta', ['code' => $code]))

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink">
            {{ __('rates.history.heading', ['code' => $code]) }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm break-words text-muted">
            {{ __('rates.history.subheading', ['code' => $code, 'count' => $availableDays]) }}
        </p>

        {{-- Currency is the only question with no sensible default here either,
        so it is the one asked on sight - the same strip as /rates. --}}
        <div class="mt-8 flex gap-2 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:overflow-visible [&::-webkit-scrollbar]:hidden">
            @foreach ($currencies as $currency)
                <a
                    href="{{ route('rates.history', ['currency' => $currency->code, 'days' => $days]) }}"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold tracking-wide uppercase transition {{ $selectedCurrency?->id === $currency->id ? 'border-primary/50 bg-primary/20 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                >
                    <span aria-hidden="true" class="text-base">{{ \App\Models\Currency::flag($currency->code) }}</span>
                    {{ $currency->code }}
                </a>
            @endforeach
        </div>

        @if ($series === [])
            <div class="mt-8 rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
                <p class="text-sm text-muted">{{ __('rates.history.more_soon') }}</p>
            </div>
        @else
            {{-- Only the ranges the data covers. A "1 year" tab over ten days of
            history would draw a chart that is mostly a straight line and
            entirely a lie, so the ones we cannot draw yet are named as coming
            rather than silently missing. --}}
            <div class="mt-6 flex flex-wrap items-center gap-2">
                @foreach ($ranges as $range)
                    <a
                        href="{{ route('rates.history', ['currency' => $code, 'days' => $range]) }}"
                        aria-current="{{ $days === $range ? 'true' : 'false' }}"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $days === $range ? 'border-primary/50 bg-primary/20 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                    >
                        {{ __('rates.history.range', ['days' => $range]) }}
                    </a>
                @endforeach

                @if ($pendingRanges !== [])
                    <span class="min-w-0 text-xs break-words text-muted">{{ __('rates.history.more_soon') }}</span>
                @endif
            </div>

            <div class="mt-6 rounded-2xl border border-placeholder bg-white p-5 sm:p-6">
                <x-rates.history-chart
                    :series="$series"
                    :lines="[
                        'best_buy' => ['label' => __('rates.history.best_buy'), 'color' => 'var(--color-primary)'],
                        'best_sell' => ['label' => __('rates.history.best_sell'), 'color' => 'var(--color-accent-red)'],
                    ]"
                    aria-label="{{ __('rates.history.heading', ['code' => $code]) }}"
                />
            </div>

            @php
                $stat = function (string $key) use ($series) {
                    $values = array_filter(array_column($series, $key), fn ($value) => $value !== null);

                    return $values === [] ? null : ['high' => max($values), 'low' => min($values)];
                };
                $buy = $stat('best_buy');
                $sell = $stat('best_sell');
            @endphp

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['label' => __('rates.history.best_buy'), 'stat' => $buy, 'change' => $buyChange, 'tone' => 'text-primary'],
                    ['label' => __('rates.history.best_sell'), 'stat' => $sell, 'change' => $sellChange, 'tone' => 'text-accent-red'],
                ] as $card)
                    @continue($card['stat'] === null)
                    <div class="min-w-0 rounded-xl border border-placeholder bg-white p-4">
                        <span class="text-xs font-semibold tracking-wider text-muted uppercase">{{ $card['label'] }}</span>

                        <p class="mt-2 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm">
                            <span class="text-muted">{{ __('rates.history.highest') }}</span>
                            <span class="font-semibold tabular-nums {{ $card['tone'] }}">{{ number_format($card['stat']['high'], 2) }}</span>
                            <span class="text-muted">{{ __('rates.history.lowest') }}</span>
                            <span class="font-semibold tabular-nums {{ $card['tone'] }}">{{ number_format($card['stat']['low'], 2) }}</span>
                        </p>

                        {{-- Only with at least two points behind it: one reading
                        against its own average is always exactly zero. --}}
                        @if ($card['change'] !== null)
                            <p class="mt-1 text-sm break-words text-muted tabular-nums">
                                {{ __('rates.history.vs_average', ['value' => ($card['change'] > 0 ? '+' : '').$card['change'], 'days' => $days]) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <p class="mt-8">
            <a href="{{ route('rates.index', ['currency' => $code]) }}" class="text-sm font-medium break-words text-primary hover:underline">
                &larr; {{ __('rates.all_heading') }}
            </a>
        </p>
    </section>
@endsection
