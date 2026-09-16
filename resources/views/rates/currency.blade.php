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
    {{-- Same page header as rates, insurance and travel. --}}
    <x-vertical-hero
        icon="arrow-left-right"
        :eyebrow="__('nav.rates')"
        :title="__('rates.landing.heading', ['code' => $code])"
        :subtitle="__('rates.landing.subheading', [
            'code' => $code,
            'count' => $topRates->count() >= 5 ? '14' : $topRates->count(),
            'time' => $updatedAt ? $updatedAt->diffForHumans() : '—',
        ])"
    />

    <section class="site-container py-12">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['label' => __('rates.landing.best_sell_heading', ['code' => $code]), 'rate' => $bestBuy?->buy_rate,
                 'org' => $bestBuy?->organization, 'note' => __('rates.landing.you_get', ['code' => $code]), 'tone' => 'text-primary'],
                ['label' => __('rates.landing.best_buy_heading', ['code' => $code]), 'rate' => $bestSell?->sell_rate,
                 'org' => $bestSell?->organization, 'note' => __('rates.landing.you_pay', ['code' => $code]), 'tone' => 'text-accent-red'],
                ['label' => __('rates.landing.average'), 'rate' => $average['average_buy'],
                 'org' => null, 'note' => __('rates.landing.you_get', ['code' => $code]), 'tone' => 'text-ink'],
            ] as $card)
                @continue($card['rate'] === null)
                <div class="min-w-0 rounded-2xl border border-border bg-surface p-5 sm:p-6">
                    <span class="text-xs font-semibold tracking-wider text-muted uppercase">{{ $card['label'] }}</span>
                    <p class="mt-2 flex items-baseline gap-2 whitespace-nowrap">
                        <span class="text-4xl font-semibold tracking-tight tabular-nums {{ $card['tone'] }}">{{ number_format((float) $card['rate'], 2) }}</span>
                        <span class="text-sm text-muted">{{ $amd }}</span>
                    </p>
                    <p class="mt-1 truncate text-sm text-muted">{{ $card['org']?->name ?? $card['note'] }}</p>
                </div>
            @endforeach
        </div>

        <h2 class="mt-12 font-heading text-xl font-semibold break-words text-ink">
            {{ __('rates.landing.where', ['code' => $code]) }}
        </h2>

        {{-- The same result card as /rates, so a currency reads the same wherever it is listed. --}}
        <div class="mt-5 flex flex-col gap-3">
            @foreach ($topRates as $i => $rate)
                <x-rates.result-card
                    :rate="(object) [
                        'organization_url' => \App\Support\Features::enabled('organizations') ? route('organizations.show', $rate->organization) : null,
                        'organization_logo' => $rate->organization->logo,
                        'organization_name' => $rate->organization->name,
                        'organization_type' => $rate->organization->type,
                        'organization_reviews_count' => 0,
                        'organization_reviews_avg_rating' => 0,
                        'buy_rate' => $rate->buy_rate,
                        'sell_rate' => $rate->sell_rate,
                        'spread' => $rate->getSpread(),
                        'scraped_at' => $rate->scraped_at?->toIso8601String(),
                        'changed_at' => null,
                        'branch' => null,
                    ]"
                    :best="$i === 0"
                    :badge="__('rates.best_badge')"
                    :stale="$rate->scraped_at && $rate->scraped_at->diffInHours(now()) >= 24"
                />
            @endforeach
        </div>

        <div class="mt-6 flex flex-wrap gap-4">
            <a href="{{ route('rates.index', ['currency' => $code]) }}" class="btn btn-primary break-words">
                {{ __('rates.landing.compare_all', ['code' => $code]) }}
            </a>
            <a href="{{ route('rates.history', ['currency' => $code]) }}" class="inline-flex min-h-11 items-center text-sm font-medium break-words text-primary hover:underline">
                {{ __('rates.landing.see_history', ['code' => $code]) }} &rarr;
            </a>
        </div>

        @if ($series !== [])
            <div class="mt-10 rounded-2xl border border-border bg-surface p-5 sm:p-6">
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

        <p class="mt-8 border-t border-border pt-5 text-xs leading-relaxed break-words text-muted">
            {{ __('rates.disclaimer') }}
        </p>
    </section>

    {{-- Only what we can actually stand behind: the pair, the rate, and when it was read. --}}
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
