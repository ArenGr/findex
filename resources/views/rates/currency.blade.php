@extends('layouts.app')

@php
    $code = $currency->code;
    $amd = __('exchange_quotes.request.amd');
@endphp

@section('title', __('rates.landing.heading', ['code' => $code]) . ' — Findex')
@section('description', __('rates.landing.meta', [
    'code' => $code,
    'buy' => $bestBuy ? number_format((float) $bestBuy->buy_rate, 2) : '—',
    'sell' => $bestSell ? number_format((float) $bestSell->sell_rate, 2) : '—',
]))

@section('content')
    {{--
        This page exists to be found. Somebody searching "USD to AMD rate today"
        wants the number, so the number is the first thing here and the
        comparison tool is a link rather than the opening screen.
    --}}
    <section class="mx-auto max-w-4xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink">
            {{ __('rates.landing.heading', ['code' => $code]) }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm break-words text-muted">
            {{ __('rates.landing.subheading', [
                'code' => $code,
                'count' => $topRates->count() >= 5 ? '14' : $topRates->count(),
                'time' => $updatedAt ? $updatedAt->diffForHumans() : '—',
            ]) }}
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['label' => __('rates.landing.best_sell_heading', ['code' => $code]), 'rate' => $bestBuy?->buy_rate,
                 'org' => $bestBuy?->organization, 'note' => __('rates.landing.you_get', ['code' => $code]), 'tone' => 'text-primary'],
                ['label' => __('rates.landing.best_buy_heading', ['code' => $code]), 'rate' => $bestSell?->sell_rate,
                 'org' => $bestSell?->organization, 'note' => __('rates.landing.you_pay', ['code' => $code]), 'tone' => 'text-accent-red'],
                ['label' => __('rates.landing.average'), 'rate' => $average['average_buy'],
                 'org' => null, 'note' => __('rates.landing.you_get', ['code' => $code]), 'tone' => 'text-ink'],
            ] as $card)
                @continue($card['rate'] === null)
                <div class="min-w-0 rounded-xl border border-placeholder bg-white p-5">
                    <span class="text-xs font-semibold tracking-wider text-muted uppercase">{{ $card['label'] }}</span>
                    <p class="mt-2 flex items-baseline gap-2 whitespace-nowrap">
                        <span class="text-3xl font-semibold tracking-tight tabular-nums {{ $card['tone'] }}">{{ number_format((float) $card['rate'], 2) }}</span>
                        <span class="text-sm text-muted">{{ $amd }}</span>
                    </p>
                    <p class="mt-1 truncate text-sm text-muted">{{ $card['org']?->name ?? $card['note'] }}</p>
                </div>
            @endforeach
        </div>

        <h2 class="mt-12 font-heading text-xl font-semibold break-words text-ink">
            {{ __('rates.landing.where', ['code' => $code]) }}
        </h2>

        {{-- Five rows, not fourteen: the full comparison is one click away, and
        two pages competing for the same search help neither. --}}
        <div class="mt-4 overflow-x-auto rounded-xl border border-placeholder">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-placeholder bg-placeholder/25 text-xs font-semibold tracking-wider text-muted uppercase">
                        <th class="px-4 py-3 text-left sm:px-6">{{ __('rates.provider_column') }}</th>
                        <th class="px-4 py-3 text-right sm:px-6">{{ __('rates.buy_column') }}</th>
                        <th class="px-4 py-3 text-right sm:px-6">{{ __('rates.sell_column') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topRates as $rate)
                        <tr class="border-b border-placeholder last:border-b-0">
                            <td class="px-4 py-4 sm:px-6">
                                <a href="{{ route('organizations.show', $rate->organization) }}" class="flex min-h-11 items-center font-medium break-words text-ink hover:text-primary">
                                    {{ $rate->organization->name }}
                                </a>
                            </td>
                            <td class="px-4 py-4 text-right text-base font-medium text-ink tabular-nums sm:px-6">{{ number_format((float) $rate->buy_rate, 2) }}</td>
                            <td class="px-4 py-4 text-right text-base font-medium tabular-nums text-accent-red sm:px-6">{{ number_format((float) $rate->sell_rate, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-wrap gap-4">
            <a href="{{ route('rates.index', ['currency' => $code]) }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold break-words text-white transition hover:bg-primary-dark">
                {{ __('rates.landing.compare_all', ['code' => $code]) }}
            </a>
            <a href="{{ route('rates.history', ['currency' => $code]) }}" class="inline-flex min-h-11 items-center text-sm font-medium break-words text-primary hover:underline">
                {{ __('rates.landing.see_history', ['code' => $code]) }} &rarr;
            </a>
        </div>

        @if ($series !== [])
            <div class="mt-10 rounded-2xl border border-placeholder bg-white p-5 sm:p-6">
                <x-rates.history-chart
                    :series="$series"
                    :lines="[
                        'best_buy' => ['label' => __('rates.history.best_buy'), 'color' => 'var(--color-primary)'],
                        'best_sell' => ['label' => __('rates.history.best_sell'), 'color' => 'var(--color-accent-red)'],
                    ]"
                    aria-label="{{ __('rates.history.title', ['code' => $code]) }}"
                />
            </div>
        @endif

        <p class="mt-8 border-t border-placeholder pt-5 text-xs leading-relaxed break-words text-muted">
            {{ __('rates.disclaimer') }}
        </p>
    </section>

    {{-- Only what we can actually stand behind: the pair, the rate, and when it
    was read. No aggregate rating, no offer count, nothing Google would be
    right to treat as decoration. --}}
    @if ($bestSell)
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'ExchangeRateSpecification',
                'currency' => $code,
                'currentExchangeRate' => [
                    '@type' => 'UnitPriceSpecification',
                    'price' => (float) $bestSell->sell_rate,
                    'priceCurrency' => 'AMD',
                ],
                'url' => route('rates.currency', ['currency' => strtolower($code)]),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endif
@endsection
