@extends('layouts.app')

@section('title', __('exchange_quotes.results.heading') . ' — Findex')

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

    // Money formatting shared by the whole page, defined here rather than
    // beside its first use: this view has several top-level branches and the
    // helpers are needed in more than one of them.
    $amd = __('exchange_quotes.request.amd');
    $money = fn (float $value) => $value < 1000 ? number_format($value, 2) : number_format($value);
    $totalLabel = __($wantsHigh ? 'exchange_quotes.value.you_receive' : 'exchange_quotes.value.you_pay');

    // Answered offices first (most recent reply first), declined ones last -
    // same reasoning as tourism/show.blade.php.
    $statusRank = fn ($response) => match (true) {
        $response->has_replied => 2,
        $response->is_declined => 0,
        default => 1,
    };

    $sortedResponses = $exchangeQuoteRequest->responses
        ->sortByDesc(fn ($response) => [$statusRank($response), $response->responded_at?->timestamp ?? 0])
        ->values();

    $repliedCount = $sortedResponses->where('has_replied', true)->count();

    // Whether a HIGHER offered rate is better for the customer, or a LOWER
    // one - depends on which side of the trade they're on. Selling foreign
    // currency (rate_field=buy_rate, the office's buy rate applies): more
    // AMD per unit is better. Buying foreign currency (sell_rate): fewer
    // AMD per unit is better. This flips every "best"/"sort" comparison
    // below, unlike travel where cheaper is unconditionally better.
    $bestIsHigher = $exchangeQuoteRequest->rate_field === 'buy_rate';

    // Data the comparison table needs, available to Alpine without a round
    // trip - same pattern as tourism/show.blade.php's $comparableData.
    $comparableData = $sortedResponses
        ->where('has_replied', true)
        ->map(fn ($response) => [
            'id' => $response->id,
            'name' => $response->organization->name,
            'initials' => Str::of($response->organization->name)->substr(0, 2)->upper()->toString(),
            'logo' => $response->organization->logo,
            'rate' => number_format((float) $response->offered_rate, 2) . ' ' . __('exchange_quotes.request.amd'),
            'notes' => $response->reply_text,
        ])
        ->values();

    // Drives the filter/sort/collapse controls client-side - same pattern
    // as tourism/show.blade.php's $responseStats.
    $responseStats = $sortedResponses
        ->map(fn ($response) => [
            'id' => $response->id,
            'hasReplied' => $response->has_replied,
            'hasImproved' => $response->has_improved_rate,
            'offeredRate' => $response->has_replied ? (float) $response->offered_rate : null,
            'repliedAt' => $response->responded_at?->timestamp,
        ])
        ->values();

    // The single best reply overall - only meaningful once there are at
    // least 2 replies to compare against each other, same reasoning as
    // tourism/show.blade.php's $bestResponseId.
    $comparableRates = $responseStats->where('hasReplied', true);
    $best = $comparableRates->count() >= 2
        ? ($bestIsHigher ? $comparableRates->sortByDesc('offeredRate')->first() : $comparableRates->sortBy('offeredRate')->first())
        : null;
    $bestResponseId = $best['id'] ?? null;
@endphp

@section('content')
    <section
        class="mx-auto max-w-7xl px-6 py-16 lg:px-10"
        x-data="{
            selected: [],
            comparable: @js($comparableData),
            stats: @js($responseStats),
            expanded: [],
            bestIsHigher: {{ $bestIsHigher ? 'true' : 'false' }},
            {{-- Defaults to showing only answered offices, but only when
            there's at least one - otherwise a page loaded right after
            submission (nobody's replied yet) would show an empty
            "no matches" state instead of the reassuring waiting list. --}}
            filterAnswered: {{ $repliedCount > 0 ? 'true' : 'false' }},
            filterImproved: false,
            sortBy: 'recent',
            toggleExpand(id) {
                this.expanded = this.expanded.includes(id) ? this.expanded.filter((x) => x !== id) : [...this.expanded, id];
            },
            statFor(id) {
                return this.stats.find((s) => s.id === id) || {};
            },
            isVisible(id) {
                const s = this.statFor(id);
                if (this.filterAnswered && !s.hasReplied) return false;
                if (this.filterImproved && !s.hasImproved) return false;
                return true;
            },
            orderFor(id) {
                // CSS order needs a plain integer - unpriced items fall back
                // to a large-but-finite number so they sort last regardless
                // of direction (see the bestIsHigher note above @php).
                const s = this.statFor(id);
                if (this.sortBy === 'rate_best') {
                    if (s.offeredRate === null) return 999999;
                    return this.bestIsHigher ? -s.offeredRate : s.offeredRate;
                }
                return -(s.repliedAt ?? 0);
            },
            get visibleCount() {
                return this.stats.filter((s) => this.isVisible(s.id)).length;
            },
            get bestSelectedId() {
                const priced = this.stats.filter((s) => this.selected.includes(s.id) && s.offeredRate !== null);
                if (priced.length < 2) return null;
                return priced.reduce((best, s) => {
                    const better = this.bestIsHigher ? s.offeredRate > best.offeredRate : s.offeredRate < best.offeredRate;
                    return better ? s : best;
                }).id;
            },
        }"
    >
        @if (session('status') === 'exchange-quote-submitted')
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{-- session('contacted_count') is the real, synchronously-known
                     partner match count from the controller - see
                     tourism/show.blade.php's identical comment for why. --}}
                {{ __('exchange_quotes.results.submitted', ['count' => session('contacted_count', $exchangeQuoteRequest->responses->count())]) }}
            </div>
        @endif

        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
            <div class="min-w-0">
                <h1 class="font-heading text-2xl font-bold break-words text-ink lg:text-3xl">{{ __('exchange_quotes.results.heading') }}</h1>

                {{-- The number this whole feature exists to produce. Only shown
                when somebody actually beat the open market: "Findex got you 0
                more" is not a claim worth making. --}}
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

            {{-- True of this system as built: SendExchangeQuoteToPartnersJob
            sends the amount, the direction and the city, and respond.blade.php
            shows the office nothing else. --}}
            <p class="flex min-w-0 items-start gap-2 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm break-words text-muted md:max-w-xs">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                    <rect width="18" height="11" x="3" y="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                {{ __('exchange_quotes.value.anonymous_note') }}
            </p>
        </div>

        @php($accepted = $exchangeQuoteRequest->responses->firstWhere('is_accepted', true))

        {{--
            The code, and nothing else the counter needs. No name, no email,
            no phone - the office looks this up against the request it already
            answered, which is the whole handshake.
        --}}
        @if ($accepted)
            <div class="mt-6 overflow-hidden rounded-2xl border-2 border-primary/40 bg-primary/5">
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-4 px-6 py-5">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.accept.your_code') }}</p>
                        <p class="mt-1 font-heading text-3xl font-bold tracking-tight break-words text-ink sm:text-4xl">
                            {{ $accepted->redemption_code ?? $exchangeQuoteRequest->public_code }}
                        </p>
                        <p class="mt-2 text-sm break-words text-muted">{{ __('exchange_quotes.accept.show_at_counter') }}</p>
                    </div>

                    <dl class="min-w-0 space-y-1 text-sm">
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-muted">{{ $accepted->organization->name }}</dt>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-muted">{{ __('exchange_quotes.accept.rate_agreed') }}:</dt>
                            <dd class="font-semibold text-ink tabular-nums">{{ number_format((float) $accepted->offered_rate, 2) }} {{ $amd }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-muted">{{ __('exchange_quotes.request.amount') }}:</dt>
                            <dd class="text-ink tabular-nums">{{ number_format((float) $exchangeQuoteRequest->amount, 2) }} {{ $exchangeQuoteRequest->currency->code }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-muted">{{ __('exchange_quotes.accept.valid_until') }}:</dt>
                            <dd class="text-ink">{{ $exchangeQuoteRequest->expires_at->translatedFormat('d F, H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        @endif

        {{-- Request summary "ticket" --}}
        <div class="mt-6 rounded-2xl border border-placeholder p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">💱</span>
                <div class="min-w-0">
                    <p class="font-heading font-semibold text-ink">
                        {{ number_format((float) $exchangeQuoteRequest->amount, 2) }} {{ $exchangeQuoteRequest->currency->code }}
                        &middot;
                        {{ __('exchange_quotes.request.direction_' . $exchangeQuoteRequest->rate_field, ['currency' => $exchangeQuoteRequest->currency->code]) }}
                    </p>
                    @if ($exchangeQuoteRequest->notes)
                        <p class="mt-0.5 truncate text-sm text-muted">{{ $exchangeQuoteRequest->notes }}</p>
                    @endif
                </div>
            </div>

            {{-- What they would get without asking anyone, so every offer below
            has something to be measured against. --}}
            @if ($publicBest !== null)
                <dl class="mt-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-t border-placeholder pt-3 text-sm">
                    <dt class="min-w-0 break-words text-muted">{{ __('exchange_quotes.value.public_best') }}</dt>
                    <dd class="tabular-nums text-ink">
                        {{ number_format($publicBest, 2) }} {{ $amd }}
                        <span class="text-muted">&middot; {{ $money($publicBestTotal) }} {{ $amd }} {{ mb_strtolower($totalLabel) }}</span>
                    </dd>
                </dl>
            @endif

            <p class="mt-3 border-t border-placeholder pt-3 text-xs {{ $exchangeQuoteRequest->is_open ? 'text-primary' : 'text-subtle' }}">
                @if ($exchangeQuoteRequest->is_open)
                    {{ __('exchange_quotes.results.expires_note', ['date' => $exchangeQuoteRequest->expires_at->translatedFormat('d F Y'), 'countdown' => $exchangeQuoteRequest->closes_in]) }}
                @else
                    {{ __('exchange_quotes.results.closed_note', ['date' => $exchangeQuoteRequest->expires_at->translatedFormat('d F Y')]) }}
                @endif
            </p>
        </div>

        @if ($sortedResponses->isNotEmpty())
            <div class="mt-6 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    @click="filterAnswered = !filterAnswered"
                    :class="filterAnswered ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                >
                    {{ __('exchange_quotes.results.filter_answered_only') }}
                </button>
                <button
                    type="button"
                    @click="filterImproved = !filterImproved"
                    :class="filterImproved ? 'bg-primary text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                >
                    ✨ {{ __('exchange_quotes.results.filter_improved_only') }}
                </button>

                <span class="mx-1 h-4 w-px bg-placeholder"></span>

                <select
                    x-model="sortBy"
                    class="rounded-full border border-placeholder bg-white px-3 py-1.5 text-xs font-medium text-ink focus:border-primary focus:outline-none"
                >
                    <option value="recent">{{ __('exchange_quotes.results.sort_recent') }}</option>
                    <option value="rate_best">{{ __('exchange_quotes.results.sort_rate_best') }}</option>
                </select>
            </div>

            @if ($repliedCount >= 2)
                <p class="mt-3 text-sm text-muted">{{ __('exchange_quotes.results.compare_hint') }}</p>
            @endif

            <div x-show="visibleCount === 0" x-cloak class="mt-4 rounded-2xl border border-dashed border-placeholder p-8 text-center">
                <p class="text-sm text-muted">{{ __('exchange_quotes.results.no_matches_filtered') }}</p>
            </div>
        @endif

        <div class="mt-4 flex flex-col gap-4">
            @forelse ($sortedResponses as $response)
                <div
                    x-show="isVisible({{ $response->id }})"
                    :style="{ order: orderFor({{ $response->id }}) }"
                    x-cloak
                    class="rounded-2xl border shadow-sm transition {{ $response->is_declined ? 'opacity-60' : '' }}"
                    @if ($response->has_replied)
                        :class="selected.includes({{ $response->id }})
                            ? 'border-primary ring-2 ring-primary/20'
                            : ({{ $response->id === $bestResponseId ? 'true' : 'false' }} ? 'border-accent-yellow ring-1 ring-accent-yellow/40' : 'border-placeholder')"
                    @else
                        :class="'border-placeholder'"
                    @endif
                >
                    <button
                        type="button"
                        @click="toggleExpand({{ $response->id }})"
                        :aria-expanded="expanded.includes({{ $response->id }}).toString()"
                        class="flex w-full items-center justify-between gap-4 p-5 text-left"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            @if ($response->organization->logo)
                                <img src="{{ $response->organization->logo }}" alt="{{ $response->organization->name }}" class="h-10 w-10 shrink-0 rounded-full object-contain">
                            @else
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                                    {{ Str::of($response->organization->name)->substr(0, 2)->upper() }}
                                </div>
                            @endif
                            <div class="min-w-0">
                                <span class="block truncate font-medium text-ink">{{ $response->organization->name }}</span>
                                @if ($response->has_replied)
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                        <span class="font-heading text-sm font-bold text-primary">
                                            {{ __('exchange_quotes.results.from_rate', ['rate' => number_format((float) $response->offered_rate, 2)]) }}
                                        </span>
                                        @if ($response->id === $bestResponseId)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-accent-yellow/30 px-2 py-0.5 text-xs font-semibold text-ink">
                                                🏆 {{ __('exchange_quotes.results.best_rate_badge') }}
                                            </span>
                                        @endif
                                        @if ($response->has_improved_rate)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-accent-yellow/20 px-2 py-0.5 text-xs font-medium text-ink">
                                                ✨ {{ __('exchange_quotes.results.improved_badge') }}
                                            </span>
                                        @endif
                                        @if ($response->is_accepted)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-white">
                                                {{ __('exchange_quotes.accept.chosen') }} &middot; {{ $response->redemption_code }}
                                            </span>
                                        @endif
                                    </div>

                                    @if (isset($offerValues[$response->id]))
                                        {{-- Below sm the header row has no room
                                        for a second column, so the total sits
                                        under the rate instead of vanishing. --}}
                                        <span class="mt-1 block text-sm whitespace-nowrap text-ink tabular-nums sm:hidden">
                                            {{ $money($offerValues[$response->id]['total']) }} <span class="text-xs text-muted">{{ $amd }}</span>
                                            @if ($offerValues[$response->id]['extra'] !== null && $offerValues[$response->id]['extra'] >= 1)
                                                <span class="font-semibold text-primary">{{ __('exchange_quotes.value.extra', ['amount' => $money($offerValues[$response->id]['extra']), 'currency' => $amd]) }}</span>
                                            @endif
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- The money in the collapsed row, not only once the
                        card is opened: comparing three offers is the point, and
                        it should not take three clicks. --}}
                        @if ($response->has_replied && isset($offerValues[$response->id]))
                            @php($headline = $offerValues[$response->id])
                            <div class="ml-auto hidden shrink-0 text-right sm:block">
                                <span class="block font-heading text-base font-bold whitespace-nowrap text-ink tabular-nums">
                                    {{ $money($headline['total']) }} <span class="text-xs font-normal text-muted">{{ $amd }}</span>
                                </span>
                                @if ($headline['extra'] !== null && $headline['extra'] >= 1)
                                    <span class="block text-xs font-semibold whitespace-nowrap text-primary tabular-nums">
                                        {{ __('exchange_quotes.value.extra', ['amount' => $money($headline['extra']), 'currency' => $amd]) }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div class="flex shrink-0 items-center gap-3">
                            @if ($response->has_replied)
                                <span class="hidden shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary sm:flex">
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                                    {{ __('exchange_quotes.results.replied_label', ['time' => $response->responded_at->diffForHumans()]) }}
                                </span>
                            @elseif ($response->is_declined)
                                <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-subtle">
                                    {{ __('exchange_quotes.results.declined_label') }}
                                </span>
                            @else
                                <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-muted">
                                    <span class="h-1.5 w-1.5 motion-safe:animate-pulse rounded-full bg-subtle"></span>
                                    {{ __('exchange_quotes.results.waiting_label') }}
                                </span>
                            @endif

                            <svg
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                class="h-5 w-5 shrink-0 text-subtle transition-transform"
                                :class="expanded.includes({{ $response->id }}) ? 'rotate-180' : ''"
                                :aria-label="expanded.includes({{ $response->id }}) ? '{{ __('exchange_quotes.results.collapse_hint') }}' : '{{ __('exchange_quotes.results.expand_hint') }}'"
                            >
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>

                    <div x-show="expanded.includes({{ $response->id }})" x-cloak x-transition class="border-t border-placeholder px-5 pb-5">
                    @if ($response->has_replied)
                        <p class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-primary sm:hidden">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                            {{ __('exchange_quotes.results.replied_label', ['time' => $response->responded_at->diffForHumans()]) }}
                        </p>

                        <dl class="mt-3 space-y-1 text-sm text-ink">
                            <div><dt class="inline text-subtle">{{ __('exchange_quotes.results.posted_rate_label') }}:</dt> <dd class="inline">{{ number_format((float) $response->posted_rate, 2) }} {{ $amd }}</dd></div>
                            <div><dt class="inline text-subtle">{{ __('exchange_quotes.results.offered_rate_label') }}:</dt> <dd class="inline font-semibold text-primary">{{ number_format((float) $response->offered_rate, 2) }} {{ $amd }}</dd></div>
                        </dl>

                        {{-- The rate turned into money, which is the comparison
                        the visitor is actually making. A rate 1.20 better is
                        abstract; 6,000 dram is not. --}}
                        @if (isset($offerValues[$response->id]))
                            @php($value = $offerValues[$response->id])
                            <div class="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1 rounded-xl bg-primary/5 px-4 py-3">
                                <span class="text-xs font-semibold tracking-wider text-muted uppercase">{{ $totalLabel }}</span>
                                <span class="font-heading text-lg font-bold whitespace-nowrap text-ink tabular-nums">
                                    {{ $money($value['total']) }} <span class="text-xs font-normal text-muted">{{ $amd }}</span>
                                </span>

                                @if ($value['extra'] !== null && $value['extra'] >= 1)
                                    <span class="inline-flex items-center gap-1 text-sm font-semibold break-words text-primary">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                                            <path d="M16 7h6v6" /><path d="m22 7-8.5 8.5-5-5L2 17" />
                                        </svg>
                                        {{ __('exchange_quotes.value.extra', ['amount' => $money($value['extra']), 'currency' => $amd]) }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        @if ($response->reply_text)
                            <p class="mt-3 rounded-xl bg-primary/5 px-4 py-3 text-sm leading-relaxed text-ink">{{ $response->reply_text }}</p>
                        @endif

                        {{-- Only while the request is open: once it closes the
                        office is no longer holding that rate, and a button that
                        promises otherwise is worse than no button. --}}
                        @if ($exchangeQuoteRequest->is_open)
                            <form method="POST" action="{{ $acceptUrl($response) }}" class="mt-4">
                                @csrf
                                <button
                                    type="submit"
                                    @class([
                                        'w-full rounded-full px-6 py-2.5 text-sm font-semibold break-words transition sm:w-auto',
                                        'bg-primary text-white hover:bg-primary-dark' => ! $response->is_accepted,
                                        'border border-primary/50 bg-white text-ink hover:bg-placeholder/25' => $response->is_accepted,
                                    ])
                                >
                                    {{ $response->is_accepted ? __('exchange_quotes.accept.choose_other') : __('exchange_quotes.accept.choose') }}
                                </button>
                            </form>
                        @endif

                        @if ($repliedCount >= 2)
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-ink">
                                <input
                                    type="checkbox"
                                    value="{{ $response->id }}"
                                    x-model.number="selected"
                                    :disabled="!selected.includes({{ $response->id }}) && selected.length >= 3"
                                    class="rounded border-border-muted text-primary focus:ring-primary disabled:opacity-40"
                                >
                                {{ __('exchange_quotes.results.add_to_compare') }}
                            </label>
                        @endif
                    @elseif ($response->is_declined)
                        <p class="mt-4 text-sm text-subtle">{{ __('exchange_quotes.results.declined_hint') }}</p>
                    @else
                        <p class="mt-4 text-sm text-subtle">{{ __('exchange_quotes.results.no_reply_yet') }}</p>
                    @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                    <p class="text-sm text-muted">{{ __('exchange_quotes.results.no_responses_yet') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Sticky compare bar --}}
        <div
            x-show="selected.length >= 2"
            x-cloak
            x-transition
            class="sticky bottom-4 mt-6 flex items-center justify-between gap-4 rounded-2xl border border-primary/30 bg-white p-4 shadow-lg"
        >
            <span class="text-sm font-medium text-ink">
                <span x-text="selected.length"></span> {{ __('exchange_quotes.results.quotes_selected') }}
            </span>
            <div class="flex items-center gap-4">
                <button type="button" @click="selected = []" class="text-xs font-medium text-subtle hover:text-ink">
                    {{ __('exchange_quotes.results.compare_bar_clear') }}
                </button>
                <a href="#compare-table" class="bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                    {{ __('exchange_quotes.results.compare_bar_button') }}
                </a>
            </div>
        </div>

        <p x-show="selected.length >= 3" x-cloak class="mt-2 text-center text-xs text-subtle">
            {{ __('exchange_quotes.results.compare_max_reached') }}
        </p>

        {{-- Side-by-side comparison table --}}
        <div x-show="selected.length >= 2" x-cloak id="compare-table" class="mt-10 scroll-mt-24">
            <h2 class="font-heading text-lg font-semibold text-ink">{{ __('exchange_quotes.results.compare_heading') }}</h2>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-placeholder">
                <table class="w-full min-w-[420px] border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="w-36 shrink-0"></th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <th
                                    class="border-b border-placeholder px-4 py-4 text-left align-bottom"
                                    :class="item.id === bestSelectedId ? 'bg-accent-yellow/20' : 'bg-placeholder/10'"
                                >
                                    <div class="flex items-center gap-2">
                                        <template x-if="item.logo">
                                            <img :src="item.logo" alt="" class="h-8 w-8 shrink-0 rounded-full object-contain">
                                        </template>
                                        <template x-if="!item.logo">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white" x-text="item.initials"></span>
                                        </template>
                                        <span class="font-semibold text-ink" x-text="item.name"></span>
                                    </div>
                                    <span x-show="item.id === bestSelectedId" x-cloak class="mt-1 inline-flex items-center gap-1 rounded-full bg-accent-yellow/30 px-2 py-0.5 text-xs font-semibold text-ink">
                                        🏆 {{ __('exchange_quotes.results.best_rate_badge') }}
                                    </span>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('exchange_quotes.results.compare_row_rate') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4" :class="item.id === bestSelectedId ? 'bg-accent-yellow/10' : ''">
                                    <span class="font-heading text-lg font-bold text-primary" x-text="item.rate"></span>
                                </td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left align-top text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('exchange_quotes.results.compare_row_reply') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="max-w-[240px] border-t border-placeholder px-4 py-4 align-top text-sm leading-relaxed text-ink" x-text="item.notes || '{{ __('exchange_quotes.results.compare_no_reply') }}'"></td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-subtle">{{ __('exchange_quotes.results.bookmark_hint') }}</p>
    </section>
@endsection
