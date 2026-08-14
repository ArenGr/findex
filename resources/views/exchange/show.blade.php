@extends('layouts.app')

@section('title', __('exchange_quotes.offers.heading') . ' — Findex')

@php
    // Guests reach this page by signature and have no session to authorize
    // them, so the action they post to has to carry one too.
    $acceptUrl = fn ($response) => request()->hasValidSignature()
        ? \Illuminate\Support\Facades\URL::signedRoute('exchange.offers.accept', [
            'exchangeQuoteRequest' => $response->exchange_quote_request_id,
            'response' => $response->id,
        ])
        : route('exchange.offers.accept', [
            'exchangeQuoteRequest' => $response->exchange_quote_request_id,
            'response' => $response->id,
        ]);

    $amd = __('exchange_quotes.request.amd');
    $money = fn (float $value) => $value < 1000 ? number_format($value, 2) : number_format($value);
    $totalLabel = __($wantsHigh ? 'exchange_quotes.value.you_receive' : 'exchange_quotes.value.you_pay');

    $currency = $exchangeQuoteRequest->currency->code;

    // Which way the money moves. rate_field is written from the organization's
    // side - buy_rate means they buy the currency from you - so the visitor is
    // selling exactly when the organization is buying.
    [$sellingCode, $buyingCode] = $wantsHigh ? [$currency, $amd] : [$amd, $currency];

    // Best offer first, then the rest by value, then the offices still to
    // answer, then the ones that declined. Ranked rather than filtered: an
    // office that has not replied is information too, and a filter control for
    // it is a control to explain.
    $ranked = $exchangeQuoteRequest->responses->sortBy(function ($response) use ($offerValues) {
        if ($response->has_replied) {
            // Negative so the largest total sorts first within group 0.
            return [0, -($offerValues[$response->id]['total'] ?? 0)];
        }

        return [$response->is_declined ? 2 : 1, 0];
    })->values();

    $replied = $ranked->where('has_replied', true);
    $bestResponseId = $replied->first()?->id;

    $accepted = $exchangeQuoteRequest->responses->firstWhere('is_accepted', true);
@endphp

@section('content')
    <section class="mx-auto max-w-6xl px-6 py-16 lg:px-10">
        @if (session('status') === 'exchange-quote-submitted')
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm break-words text-primary">
                {{ __('exchange_quotes.results.submitted', ['count' => session('contacted_count', $exchangeQuoteRequest->responses->count())]) }}
            </div>
        @endif

        {{-- The claim, and the reason it is safe to make - side by side, because
        one is the reward and the other is the reassurance. --}}
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
            <div class="min-w-0">
                <h1 class="font-heading text-2xl font-bold break-words text-ink lg:text-3xl">{{ __('exchange_quotes.offers.heading') }}</h1>

                @if ($bestExtra !== null && $bestExtra >= 1)
                    <p class="mt-3 inline-flex items-center gap-2 rounded-lg border border-accent-yellow/50 bg-accent-yellow/15 px-4 py-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-4 w-4 shrink-0 fill-accent-yellow" aria-hidden="true">
                            <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                        </svg>
                        <span class="min-w-0 text-sm break-words text-ink">
                            {!! __('exchange_quotes.value.headline', [
                                'amount' => '<strong class="font-semibold text-primary">'.e($money($bestExtra)).'</strong>',
                                'currency' => e($amd),
                            ]) !!}
                        </span>
                    </p>
                @endif
            </div>

            {{-- True of this system as built: the fan-out job sends the amount,
            the direction and the city, and the partner page shows nothing
            else. --}}
            <p class="flex min-w-0 items-start gap-2 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm break-words text-muted md:max-w-xs">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                    <rect width="18" height="11" x="3" y="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                {{ __('exchange_quotes.value.anonymous_note') }}
            </p>
        </div>

        @if ($accepted)
            {{-- Once chosen, the code is the only thing left to act on, so it
            goes above everything else. --}}
            <div class="mt-8 rounded-2xl border-2 border-primary/40 bg-primary/5 px-6 py-5">
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.accept.your_code') }}</p>
                        <p class="mt-1 font-heading text-3xl font-bold tracking-tight break-words text-ink sm:text-4xl">{{ $accepted->redemption_code }}</p>
                        <p class="mt-2 text-sm break-words text-muted">{{ __('exchange_quotes.accept.show_at_counter') }}</p>
                    </div>
                    <dl class="min-w-0 space-y-1 text-sm">
                        <div class="break-words text-muted">{{ $accepted->organization->name }}</div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-muted">{{ __('exchange_quotes.accept.rate_agreed') }}:</dt>
                            <dd class="font-semibold text-ink tabular-nums">{{ number_format((float) $accepted->offered_rate, 2) }} {{ $amd }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-muted">{{ __('exchange_quotes.accept.valid_until') }}:</dt>
                            <dd class="text-ink">{{ $exchangeQuoteRequest->expires_at->translatedFormat('d F, H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        @endif

        <div class="mt-8 grid gap-6 lg:grid-cols-12">
            {{-- What was asked, kept beside the answers rather than above them:
            on a wide screen it stays in view while the offers are read. --}}
            <aside class="lg:col-span-4">
                <div class="rounded-2xl border border-placeholder bg-white p-6">
                    <h2 class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.offers.summary') }}</h2>

                    <div class="mt-4 flex items-end justify-between gap-3 border-b border-placeholder pb-4">
                        <div class="min-w-0">
                            <span class="block text-sm break-words text-muted">{{ __('exchange_quotes.offers.selling') }}</span>
                            <span class="font-heading text-xl font-semibold break-words text-ink">
                                {{ $wantsHigh ? number_format((float) $exchangeQuoteRequest->amount, 2).' ' : '' }}{{ $sellingCode }}
                            </span>
                        </div>
                        <span aria-hidden="true" class="shrink-0 pb-1 text-muted">&rarr;</span>
                        <div class="min-w-0 text-end">
                            <span class="block text-sm break-words text-muted">{{ __('exchange_quotes.offers.buying') }}</span>
                            <span class="font-heading text-xl font-semibold break-words text-ink">{{ $buyingCode }}</span>
                        </div>
                    </div>

                    <dl class="mt-4 space-y-3 text-sm">
                        @if ($publicBest !== null)
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                                <dt class="min-w-0 break-words text-muted">{{ __('exchange_quotes.offers.public_best') }}</dt>
                                <dd class="font-semibold text-ink tabular-nums">{{ number_format($publicBest, 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                            <dt class="min-w-0 break-words text-muted">{{ __('exchange_quotes.offers.amount_requested') }}</dt>
                            <dd class="text-ink tabular-nums">{{ number_format((float) $exchangeQuoteRequest->amount, 2) }} {{ $currency }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 border-t border-placeholder pt-3 text-xs break-words {{ $exchangeQuoteRequest->is_open ? 'text-primary' : 'text-muted' }}">
                        @if ($exchangeQuoteRequest->is_open)
                            {{ __('exchange_quotes.results.expires_note', ['date' => $exchangeQuoteRequest->expires_at->translatedFormat('d F Y'), 'countdown' => $exchangeQuoteRequest->closes_in]) }}
                        @else
                            {{ __('exchange_quotes.results.closed_note', ['date' => $exchangeQuoteRequest->expires_at->translatedFormat('d F Y')]) }}
                        @endif
                    </p>
                </div>
            </aside>

            <div class="min-w-0 space-y-4 lg:col-span-8">
                @forelse ($ranked as $response)
                    @php
                        $isBest = $response->id === $bestResponseId;
                        $value = $offerValues[$response->id] ?? null;
                    @endphp

                    {{-- Everything on the face of the card. The comparison is
                    the job of this page, and it should not take a click per
                    offer to make one. --}}
                    <article @class([
                        'relative rounded-2xl p-6 transition',
                        'border-2 border-primary/40 bg-accent-yellow/10' => $isBest,
                        'border border-placeholder bg-white hover:border-primary/50' => ! $isBest,
                        'opacity-70' => $response->is_declined,
                    ])>
                        @if ($isBest)
                            <span class="absolute -top-3 left-6 inline-flex items-center gap-1 rounded-full bg-primary px-3 py-1 text-xs font-semibold tracking-wide text-white uppercase">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-3 w-3 fill-accent-yellow" aria-hidden="true">
                                    <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                                </svg>
                                {{ __('exchange_quotes.offers.best_offer') }}
                            </span>
                        @endif

                        {{-- Name and figures share the top row; the action gets
                        its own. Four columns abreast squeezed "Capital Currency
                        House" into 175px and wrapped it over three lines - and
                        the Armenian names are longer than the English ones. --}}
                        <div class="flex flex-col gap-x-6 gap-y-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-heading text-lg font-semibold break-words text-ink">
                                    <a href="{{ route('organizations.show', $response->organization) }}" class="hover:text-primary">{{ $response->organization->name }}</a>
                                </h3>

                                @if ($response->has_replied)
                                    <p class="mt-1 text-sm break-words text-muted">
                                        {{ __('exchange_quotes.results.replied_label', ['time' => $response->responded_at->diffForHumans()]) }}
                                    </p>
                                    @if ($response->reply_text)
                                        <p class="mt-3 rounded-xl bg-placeholder/25 px-4 py-3 text-sm leading-relaxed break-words text-ink">{{ $response->reply_text }}</p>
                                    @endif
                                @else
                                    <p class="mt-1 text-sm break-words text-muted">
                                        {{ $response->is_declined ? __('exchange_quotes.offers.declined') : __('exchange_quotes.offers.waiting') }}
                                    </p>
                                @endif
                            </div>

                            @if ($response->has_replied && $value)
                                <div class="min-w-0 shrink-0 sm:text-end">
                                    <span class="block text-sm text-muted">{{ __('exchange_quotes.offers.rate') }}</span>
                                    <span @class(['font-heading text-xl font-semibold whitespace-nowrap tabular-nums', 'text-primary' => $isBest, 'text-ink' => ! $isBest])>
                                        {{ number_format((float) $response->offered_rate, 2) }}
                                    </span>
                                </div>

                                <div class="min-w-0 shrink-0 sm:text-end">
                                    <span class="block text-sm break-words text-muted">{{ $totalLabel }}</span>
                                    <span class="block font-heading text-xl font-semibold whitespace-nowrap text-ink tabular-nums">
                                        {{ $money($value['total']) }} <span class="text-sm font-normal text-muted">{{ $amd }}</span>
                                    </span>

                                    @if ($value['extra'] !== null && $value['extra'] >= 1)
                                        <span class="mt-1 inline-flex items-center gap-1 text-sm font-semibold whitespace-nowrap text-primary">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                                                <path d="M16 7h6v6" /><path d="m22 7-8.5 8.5-5-5L2 17" />
                                            </svg>
                                            {{ __('exchange_quotes.value.extra', ['amount' => $money($value['extra']), 'currency' => $amd]) }}
                                        </span>
                                    @endif
                                </div>

                            @endif
                        </div>

                        @if ($response->has_replied && $value && $exchangeQuoteRequest->is_open)
                            <form method="POST" action="{{ $acceptUrl($response) }}" class="mt-5 border-t border-placeholder/70 pt-4 sm:flex sm:justify-end">
                                @csrf
                                <button
                                    type="submit"
                                    @class([
                                        'inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg px-6 py-3 text-sm font-semibold break-words transition sm:w-auto',
                                        'bg-primary text-white hover:bg-primary-dark' => ! $response->is_accepted,
                                        'border border-primary/50 bg-white text-ink hover:bg-placeholder/25' => $response->is_accepted,
                                    ])
                                >
                                    {{ $response->is_accepted ? __('exchange_quotes.accept.choose_other') : __('exchange_quotes.accept.choose') }}
                                </button>
                            </form>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
                        <p class="text-sm break-words text-muted">{{ __('exchange_quotes.offers.none_yet') }}</p>
                    </div>
                @endforelse

                @if ($replied->isEmpty() && $ranked->isNotEmpty())
                    <p class="text-sm break-words text-muted">{{ __('exchange_quotes.offers.none_yet_note') }}</p>
                @endif
            </div>
        </div>
    </section>
@endsection
