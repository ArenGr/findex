@extends('layouts.app')

@section('title', __('exchange_quotes.results.heading') . ' — Findex')

@php
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
        class="mx-auto max-w-2xl px-6 py-16 lg:px-10"
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

        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('exchange_quotes.results.heading') }}</h1>

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
                                    </div>
                                @endif
                            </div>
                        </div>

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
                            <div><dt class="inline text-subtle">{{ __('exchange_quotes.results.posted_rate_label') }}:</dt> <dd class="inline">{{ number_format((float) $response->posted_rate, 2) }} {{ __('exchange_quotes.request.amd') }}</dd></div>
                            <div><dt class="inline text-subtle">{{ __('exchange_quotes.results.offered_rate_label') }}:</dt> <dd class="inline font-semibold text-primary">{{ number_format((float) $response->offered_rate, 2) }} {{ __('exchange_quotes.request.amd') }}</dd></div>
                        </dl>

                        @if ($response->reply_text)
                            <p class="mt-3 rounded-xl bg-primary/5 px-4 py-3 text-sm leading-relaxed text-ink">{{ $response->reply_text }}</p>
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
