@extends('layouts.app')

@section('title', __('exchange_quotes.offers.heading') . ' — Findex')

@php
    // Guests reach this page by signature and have no session to authorize
    // them, so anything they post to has to carry one too.
    $signed = fn (string $name, array $params) => request()->hasValidSignature()
        ? \Illuminate\Support\Facades\URL::signedRoute($name, $params)
        : route($name, $params);

    $acceptUrl = fn ($response) => $signed('exchange.offers.accept', [
        'exchangeQuoteRequest' => $response->exchange_quote_request_id,
        'response' => $response->id,
    ]);

    $amd = __('exchange_quotes.request.amd');
    $money = fn (float $value) => $value < 1000 ? number_format($value, 2) : number_format($value);
    $totalLabel = __($wantsHigh ? 'exchange_quotes.value.you_receive' : 'exchange_quotes.value.you_pay');

    $currency = $exchangeQuoteRequest->currency->code;
    $amount = (float) $exchangeQuoteRequest->amount;

    // Best offer first, then the rest by value, then the offices still to
    // answer, then the ones that declined. Ranked rather than filtered: an
    // office that has not replied is information too.
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
    $isOpen = $exchangeQuoteRequest->is_open;

    // Four states, and only ever one of them. The page is read at four
    // different moments in one errand, and a layout that tries to serve all of
    // them at once serves the moment you are actually in worst.
    $state = match (true) {
        $accepted !== null => 'accepted',
        $replied->isNotEmpty() => 'offers',
        $isOpen => 'waiting',
        default => 'expired',
    };

@endphp

@section('content')
    @if ($state === 'accepted')
        {{--
            Everything else on this page was about choosing. Once chosen, the
            only thing left to do is walk in and say the code, so the code is
            what the page becomes.
        --}}
        @php $value = $offerValues[$accepted->id] ?? null; @endphp

        <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
            <div class="overflow-hidden rounded-2xl border border-placeholder bg-white">
                <div class="flex flex-col items-center bg-primary px-6 py-8 text-center text-white">
                    <span class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white text-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-8 w-8" aria-hidden="true">
                            <path d="m5 13 4 4L19 7" />
                        </svg>
                    </span>
                    <h1 class="font-heading text-2xl font-bold break-words">{{ __('exchange_quotes.offers.accepted_heading') }}</h1>
                    <p class="mt-2 text-sm break-words opacity-90">{{ __('exchange_quotes.offers.accepted_body') }}</p>
                </div>

                <div class="px-6 py-8 sm:px-10">
                    <div class="grid gap-8 border-b border-placeholder pb-8 sm:grid-cols-2">
                        <div class="min-w-0">
                            <span class="block text-[11px] font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.offers.agreed_rate') }}</span>
                            <p class="mt-2 font-heading text-2xl font-bold break-words text-ink tabular-nums">{{ number_format((float) $accepted->offered_rate, 2) }}</p>
                            <p class="mt-1 text-xs break-words text-muted">{{ $currency }} / {{ $amd }}</p>
                        </div>
                        @if ($value)
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold tracking-wider text-muted uppercase">{{ $totalLabel }}</span>
                                <p class="mt-2 font-heading text-2xl font-bold break-words text-primary tabular-nums">{{ $money($value['total']) }} {{ $amd }}</p>
                                <p class="mt-1 text-xs break-words text-muted">{{ __('exchange_quotes.offers.for_amount', ['amount' => $money($amount), 'currency' => $currency]) }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-start gap-4 border-b border-placeholder py-8">
                        <x-rates.org-mark :logo="$accepted->organization->logo" :name="$accepted->organization->name" />
                        <div class="min-w-0">
                            <h2 class="font-heading text-lg font-semibold break-words text-ink">{{ $accepted->organization->name }}</h2>
                            @php $branch = $accepted->organization->branches->firstWhere('is_active', true); @endphp
                            @if ($branch?->address)
                                <p class="mt-1 text-sm break-words text-muted">{{ $branch->address }}@if ($branch->city), {{ $branch->city }}@endif</p>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-3">
                                @if ($branch?->latitude && $branch?->longitude)
                                    <a
                                        href="https://www.google.com/maps/dir/?api=1&destination={{ $branch->latitude }},{{ $branch->longitude }}"
                                        target="_blank" rel="noopener noreferrer"
                                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold break-words text-white transition hover:bg-primary-dark"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                                            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" /><circle cx="12" cy="10" r="3" />
                                        </svg>
                                        {{ __('exchange_quotes.offers.get_directions') }}
                                    </a>
                                @endif
                                <a
                                    href="{{ route('organizations.show', $accepted->organization) }}"
                                    class="inline-flex min-h-11 items-center rounded-xl border border-border-muted px-4 py-2 text-sm font-semibold break-words text-ink transition hover:bg-placeholder/25"
                                >
                                    {{ __('exchange_quotes.offers.view_office') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- The one thing the counter needs. No QR: nothing behind
                    the counter scans one, and a code you can read aloud works
                    on a cracked screen and over the phone. --}}
                    <div class="mt-8 flex flex-col items-center rounded-xl border border-placeholder bg-placeholder/20 px-6 py-8 text-center">
                        <span class="text-[11px] font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.accept.your_code') }}</span>
                        <p class="mt-4 rounded-xl border border-dashed border-border-muted bg-white px-6 py-3 font-heading text-2xl font-bold tracking-widest break-all text-ink sm:text-3xl">
                            {{ $accepted->redemption_code }}
                        </p>
                        <p class="mt-4 text-sm break-words text-muted">{{ __('exchange_quotes.accept.show_at_counter') }}</p>
                    </div>
                </div>
            </div>
        </section>

    @elseif ($state === 'waiting')
        {{--
            Nothing has arrived yet, so there is nothing to compare and no
            reason to draw a comparison table with nothing in it. What the page
            owes them here is: it was sent, this is what it is worth beating,
            and this is how long you are waiting.
        --}}
        <section class="mx-auto flex max-w-xl flex-col gap-6 px-6 py-16 lg:px-10">
            <div class="rounded-2xl border border-placeholder bg-placeholder/20 px-6 py-12 text-center">
                <h1 class="font-heading text-2xl font-bold break-words text-primary">
                    {{ $money($amount) }} {{ $currency }} <span aria-hidden="true" class="text-muted">&rarr;</span> {{ $amd }}
                </h1>

                <p class="mt-3 inline-flex items-center gap-2 text-sm break-words text-muted">
                    {{-- Motion says "still running" more directly than any
                    wording, and is dropped for anyone who asked for less of it. --}}
                    <span class="relative flex h-2.5 w-2.5 shrink-0" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-primary opacity-75 motion-safe:animate-ping"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-primary"></span>
                    </span>
                    {{ __('exchange_quotes.offers.waiting_heading') }}
                </p>

                <div class="mt-8">
                    <span class="block text-[11px] font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.offers.time_remaining') }}</span>
                    <p class="mt-1 font-heading text-3xl font-bold break-words text-ink">
                        {{ $exchangeQuoteRequest->closes_in_short }}
                    </p>
                </div>
            </div>

            @if ($publicBest !== null)
                {{-- The number every offer has to beat, so an arriving offer
                means something the second it lands. --}}
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-placeholder bg-white px-5 py-4">
                    <span class="min-w-0 text-sm break-words text-muted">{{ __('exchange_quotes.offers.public_best') }}</span>
                    <span class="font-semibold text-ink tabular-nums">
                        {{ number_format($publicBest, 2) }} {{ $amd }}
                        <span class="font-normal text-muted">({{ $money($wantsHigh ? $amount * $publicBest : $amount / $publicBest) }} {{ $wantsHigh ? $amd : $currency }})</span>
                    </span>
                </div>
            @endif

            <p class="px-4 text-center text-sm leading-relaxed break-words text-muted">
                {{ __('exchange_quotes.offers.waiting_body') }}
                @if ($ranked->isNotEmpty())
                    {{ __('exchange_quotes.offers.sent_to_count', ['count' => $ranked->count()]) }}.
                @endif
            </p>

            <form method="POST" action="{{ $signed('exchange.cancel', ['exchangeQuoteRequest' => $exchangeQuoteRequest->id]) }}" class="flex justify-center border-t border-placeholder pt-6">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center rounded-xl border border-border-muted px-6 py-3 text-sm font-semibold break-words text-muted transition hover:border-accent-red hover:text-accent-red">
                    {{ __('exchange_quotes.offers.cancel_request') }}
                </button>
            </form>
        </section>

    @elseif ($state === 'expired')
        {{--
            The window closed with nothing in it. Saying so plainly, and then
            offering the two things worth doing next, beats an empty offers
            list that looks like it is still loading.
        --}}
        <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
            <div class="flex flex-col items-center rounded-2xl border border-placeholder bg-white px-6 py-12 text-center sm:px-12">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-subtle" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
                </svg>
                <h1 class="mt-4 font-heading text-2xl font-bold break-words text-ink">
                    {{ session('status') === 'exchange-quote-cancelled' ? __('exchange_quotes.offers.cancelled_flash') : __('exchange_quotes.offers.expired_heading') }}
                </h1>
                <p class="mt-3 max-w-md text-sm leading-relaxed break-words text-muted">{{ __('exchange_quotes.offers.expired_body') }}</p>

                <div class="mt-8 flex w-full flex-col justify-center gap-3 sm:flex-row">
                    {{-- Prefilled from the request that just closed: the answer
                    to "how much, in what" has not changed since they typed it. --}}
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('better-rate-open', { detail: {{ Js::from([
                            'form' => [
                                'currency_code' => $currency,
                                'amount' => (string) round($amount, 2),
                                'rate_field' => $exchangeQuoteRequest->rate_field,
                                'preferred_city' => (string) $exchangeQuoteRequest->preferred_city,
                            ],
                            'context' => ['rate' => $publicBest ? number_format($publicBest, 2) : null, 'code' => $currency],
                        ]) }} }))"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary px-6 py-3 text-sm font-semibold break-words text-white transition hover:bg-primary-dark"
                    >
                        {{ __('exchange_quotes.offers.try_again') }}
                    </button>
                    <a
                        href="{{ route('rates.index', ['currency' => $currency]) }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-border-muted px-6 py-3 text-sm font-semibold break-words text-ink transition hover:bg-placeholder/25"
                    >
                        {{ __('exchange_quotes.offers.compare_public') }}
                    </a>
                </div>
            </div>

            @if ($publicBest !== null)
                <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-placeholder bg-placeholder/20 px-5 py-4">
                    <span class="min-w-0 text-[11px] font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.offers.public_best') }}</span>
                    <span class="font-heading text-xl font-bold text-ink tabular-nums">{{ number_format($publicBest, 2) }} <span class="text-sm font-normal text-muted">{{ $amd }}</span></span>
                </div>
            @endif

            {{-- Kept for the record, and because "what exactly did I ask for"
            is the question anyone re-reading a closed request has. --}}
            <h2 class="mt-10 border-b border-placeholder pb-2 font-heading text-lg font-semibold break-words text-ink">{{ __('exchange_quotes.offers.request_details') }}</h2>
            <dl class="mt-4 overflow-hidden rounded-xl border border-placeholder">
                @foreach ([
                    __('exchange_quotes.offers.detail_reference') => $exchangeQuoteRequest->public_code,
                    __('exchange_quotes.offers.detail_amount') => $money($amount).' '.$currency,
                    __('exchange_quotes.offers.detail_window') => __('exchange_quotes.offers.minutes_window', ['count' => (int) round($exchangeQuoteRequest->created_at->diffInMinutes($exchangeQuoteRequest->expires_at))]),
                    __('exchange_quotes.offers.detail_status') => __('exchange_quotes.offers.status_expired'),
                ] as $label => $detail)
                    <div class="flex flex-wrap justify-between gap-4 border-b border-placeholder px-5 py-4 last:border-b-0">
                        <dt class="min-w-0 text-sm break-words text-muted">{{ $label }}</dt>
                        <dd class="min-w-0 text-sm font-medium break-words text-ink tabular-nums">{{ $detail }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

    @else
        {{--
            Offers are in. This is the comparison, and it is the whole reason
            the request fanned out to more than one office.
        --}}
        <section
            class="mx-auto max-w-5xl px-6 py-16 lg:px-10"
            x-data="{
                confirming: null,
                choose(offer) { this.confirming = offer; },
                close() { this.confirming = null; },
            }"
            @keydown.escape.window="close()"
        >
            <div class="flex flex-col justify-between gap-6 border-b border-placeholder pb-8 md:flex-row md:items-end">
                <div class="min-w-0">
                    <h1 class="font-heading text-2xl font-bold break-words text-ink lg:text-3xl">
                        {{ __('exchange_quotes.accept.request_code', ['code' => $exchangeQuoteRequest->public_code]) }}
                    </h1>
                    <p class="mt-2 flex flex-wrap items-baseline gap-x-3 font-heading text-xl font-semibold break-words text-ink">
                        {{ $money($amount) }} {{ $currency }}
                        <span aria-hidden="true" class="text-muted">&rarr;</span>
                        {{ $amd }}
                    </p>
                </div>

                @if ($publicBest !== null)
                    <div class="min-w-0 md:text-end">
                        <span class="block text-[11px] font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.offers.public_best') }}</span>
                        <p class="mt-1 font-heading text-xl font-bold break-words text-subtle tabular-nums">{{ number_format($publicBest, 2) }}</p>
                    </div>
                @endif
            </div>

            {{-- True as built: the fan-out job sends the amount, the direction
            and the city, and the partner page shows the office nothing else. --}}
            <div class="mt-8 flex items-start gap-4 rounded-xl border border-placeholder bg-placeholder/20 px-5 py-4">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-5 w-5 shrink-0 text-muted" aria-hidden="true">
                    <rect width="18" height="11" x="3" y="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold break-words text-ink">{{ __('exchange_quotes.modal.privacy_title') }}</h2>
                    <p class="mt-1 text-sm leading-relaxed break-words text-muted">{{ __('exchange_quotes.modal.privacy_body') }}</p>
                </div>
            </div>

            <h2 class="mt-12 mb-6 font-heading text-lg font-semibold break-words text-ink">
                {{ __('exchange_quotes.offers.received_offers', ['count' => $replied->count()]) }}
            </h2>

            <div class="space-y-4">
                @foreach ($ranked as $response)
                    @php
                        $isBest = $response->id === $bestResponseId;
                        $value = $offerValues[$response->id] ?? null;
                    @endphp

                    <article @class([
                        'relative overflow-hidden rounded-xl p-6 transition',
                        'border border-accent-yellow/50 bg-accent-yellow/10' => $isBest,
                        'border border-placeholder bg-white hover:border-primary/50' => ! $isBest,
                        'opacity-70' => $response->is_declined,
                    ])>
                        @if ($isBest)
                            <span class="absolute inset-y-0 start-0 w-1 bg-accent-yellow" aria-hidden="true"></span>
                        @endif

                        <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                            <div class="min-w-0 flex-1">
                                @if ($isBest)
                                    <p class="mb-2 flex flex-wrap items-center gap-2">
                                        <x-rates.best-chip :label="__('exchange_quotes.offers.best_offer')" />
                                        <span class="text-[11px] font-bold tracking-wider text-ink uppercase">{{ __('exchange_quotes.offers.best_offer') }}</span>
                                        @if ($isOpen)
                                            <span class="ms-auto inline-flex items-center gap-1 text-sm break-words text-muted md:ms-4">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
                                                </svg>
                                                {{ __('exchange_quotes.offers.time_left', ['time' => $exchangeQuoteRequest->closes_in_short]) }}
                                            </span>
                                        @endif
                                    </p>
                                @endif

                                <h3 class="font-heading text-lg font-semibold break-words text-ink">
                                    <a href="{{ route('organizations.show', $response->organization) }}" class="hover:text-primary">{{ $response->organization->name }}</a>
                                </h3>

                                @if ($response->has_replied && $value)
                                    <dl class="mt-4 grid grid-cols-2 gap-6 md:grid-cols-3">
                                        <div class="min-w-0">
                                            <dt class="text-[11px] font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.offers.offered_rate') }}</dt>
                                            <dd class="mt-1 font-semibold break-words text-ink tabular-nums">{{ number_format((float) $response->offered_rate, 2) }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt class="text-[11px] font-semibold tracking-wider text-muted uppercase">{{ $totalLabel }}</dt>
                                            <dd class="mt-1 font-semibold break-words text-ink tabular-nums">{{ $money($value['total']) }} {{ $amd }}</dd>
                                        </div>
                                        @if ($value['extra'] !== null && $value['extra'] >= 1)
                                            <div class="col-span-2 min-w-0 border-t border-placeholder pt-4 md:col-span-1 md:border-t-0 md:border-s md:ps-6 md:pt-0">
                                                {{-- Named against what it beats. "You save X" with no
                                                stated baseline is a number nobody can check. --}}
                                                <dt class="text-[11px] font-semibold tracking-wider text-primary uppercase">{{ __('exchange_quotes.offers.net_gain') }}</dt>
                                                <dd class="mt-1 font-semibold break-words text-primary tabular-nums">{{ __('exchange_quotes.value.extra', ['amount' => $money($value['extra']), 'currency' => $amd]) }}</dd>
                                            </div>
                                        @endif
                                    </dl>

                                    @if ($response->reply_text)
                                        <p class="mt-4 rounded-xl bg-placeholder/25 px-4 py-3 text-sm leading-relaxed break-words text-ink">{{ $response->reply_text }}</p>
                                    @endif
                                @else
                                    <p class="mt-2 text-sm break-words text-muted">
                                        {{ $response->is_declined ? __('exchange_quotes.offers.declined') : __('exchange_quotes.offers.waiting') }}
                                    </p>
                                @endif
                            </div>

                            @if ($response->has_replied && $value && $isOpen)
                                <div class="shrink-0 md:ps-6">
                                    <button
                                        type="button"
                                        @click="choose({{ Js::from([
                                            'organization' => $response->organization->name,
                                            'rate' => number_format((float) $response->offered_rate, 2),
                                            'total' => $money($value['total']).' '.$amd,
                                            'extra' => $value['extra'] !== null && $value['extra'] >= 1 ? __('exchange_quotes.value.extra', ['amount' => $money($value['extra']), 'currency' => $amd]) : null,
                                            'action' => $acceptUrl($response),
                                        ]) }})"
                                        @class([
                                            'inline-flex min-h-11 w-full items-center justify-center rounded-xl px-8 py-3 text-sm font-semibold break-words transition md:w-auto',
                                            'bg-primary text-white hover:bg-primary-dark' => $isBest,
                                            'border border-border-muted text-primary hover:border-primary' => ! $isBest,
                                        ])
                                    >
                                        {{ __('exchange_quotes.accept.choose') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            {{--
                Accepting is the one irreversible thing on this page - it tells
                an office to hold money for you - so it is confirmed rather than
                done on a single press.
            --}}
            <div x-show="confirming" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ink/50 p-4 backdrop-blur-sm">
                <div
                    @click.outside="close()"
                    role="dialog" aria-modal="true" aria-labelledby="confirm-offer-title"
                    class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-[0_24px_40px_rgba(27,28,29,0.14)]"
                    x-transition
                >
                    <div class="border-b border-placeholder px-6 pt-6 pb-4">
                        <h2 id="confirm-offer-title" class="font-heading text-xl font-bold break-words text-ink">{{ __('exchange_quotes.offers.confirm_heading') }}</h2>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <div class="min-w-0">
                            <span class="block text-[11px] font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.offers.confirm_provider') }}</span>
                            <p class="mt-1 font-medium break-words text-ink" x-text="confirming?.organization"></p>
                        </div>

                        <div class="relative space-y-4 overflow-hidden rounded-xl border border-placeholder p-5">
                            <span class="absolute inset-y-0 start-0 w-1 bg-primary" aria-hidden="true"></span>
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <span class="text-sm break-words text-muted">{{ __('exchange_quotes.offers.rate') }}</span>
                                <span class="font-semibold text-ink tabular-nums" x-text="confirming?.rate"></span>
                            </div>
                            <div class="flex flex-wrap items-end justify-between gap-4 border-t border-placeholder pt-4">
                                <span class="text-sm break-words text-muted">{{ __('exchange_quotes.offers.confirm_receive') }}</span>
                                <span class="font-heading text-xl font-bold break-words text-ink tabular-nums" x-text="confirming?.total"></span>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg bg-accent-yellow/15 px-3 py-2" x-show="confirming?.extra">
                                <span class="text-sm break-words text-muted">{{ __('exchange_quotes.offers.confirm_gain') }}</span>
                                <span class="font-semibold text-primary tabular-nums" x-text="confirming?.extra"></span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" :action="confirming?.action" class="flex flex-col-reverse gap-3 border-t border-placeholder bg-placeholder/20 px-6 py-4 sm:flex-row sm:justify-end">
                        @csrf
                        <button type="button" @click="close()" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-border-muted px-5 py-2.5 text-sm font-semibold break-words text-ink transition hover:bg-placeholder/40">
                            {{ __('exchange_quotes.modal.cancel') }}
                        </button>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold break-words text-white transition hover:bg-primary-dark">
                            {{ __('exchange_quotes.offers.confirm_submit') }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    @endif
@endsection
