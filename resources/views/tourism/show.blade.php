@extends('layouts.app')

@section('title', __('tourism.results.heading') . ' — Findex')

@php
    $flags = [
        'AE' => '🇦🇪', 'EG' => '🇪🇬', 'GE' => '🇬🇪', 'GR' => '🇬🇷',
        'TH' => '🇹🇭', 'CY' => '🇨🇾', 'IT' => '🇮🇹', 'FR' => '🇫🇷', 'ES' => '🇪🇸',
    ];

    // Replied agencies first (most recent reply first), declined ones last,
    // so a traveler sees actual quotes immediately instead of scrolling past
    // pending or declined ones.
    $statusRank = fn ($response) => match (true) {
        $response->has_replied => 2,
        $response->is_declined => 0,
        default => 1,
    };

    $sortedResponses = $quoteRequest->responses
        ->sortByDesc(fn ($response) => [$statusRank($response), $response->responded_at?->timestamp ?? 0])
        ->values();

    $repliedCount = $sortedResponses->where('has_replied', true)->count();

    // Data the comparison table needs, available to Alpine without a
    // round trip - everything's already loaded on this one page. A
    // response can hold several suggestions now (see QuoteSuggestion) -
    // the comparison table (one figure per org) uses the cheapest, since
    // that's most relevant to a budget-conscious traveler comparing
    // across agencies. The full list of suggestions still shows in each
    // response's own card below, this is only a simplification for the
    // side-by-side view.
    $comparableData = $sortedResponses
        ->where('has_replied', true)
        ->map(function ($response) {
            $cheapest = $response->cheapestSuggestion();

            return [
                'id' => $response->id,
                'name' => $response->organization->name,
                'initials' => Str::of($response->organization->name)->substr(0, 2)->upper()->toString(),
                'logo' => $response->organization->logo,
                'price' => $cheapest
                    ? rtrim(rtrim((string) $cheapest->price_amount, '0'), '.') . ' ' . $cheapest->price_currency
                    : null,
                'hotel' => $cheapest?->offered_hotel_name,
                'flight' => $cheapest?->flight_details,
                'inclusions' => $cheapest?->inclusions,
                'notes' => $response->reply_text,
            ];
        })
        ->values();

    // Drives the filter/sort/collapse controls client-side (see the
    // x-data on the section below) - everything needed is already loaded
    // on this one page, so filtering happens instantly with no round
    // trip. cheapestPriceConverted normalizes into the visitor's
    // preferred currency purely for sorting - the price actually shown
    // in each card still uses the suggestion's own native currency.
    $responseStats = $sortedResponses
        ->map(function ($response) use ($currencyConverter) {
            $cheapest = $response->has_replied ? $response->cheapestSuggestion() : null;
            $cheapestConverted = null;

            if ($cheapest) {
                // Normalized to AMD specifically (not the visitor's display
                // currency, e.g. USD for English locales) - AMD is the
                // common denominator CurrencyConverter's own rate data
                // already pivots through, so suggestions already priced in
                // AMD compare directly with zero conversion risk, and only
                // genuinely foreign currencies depend on rate data being
                // available. Left null (rather than falling back to the
                // raw, un-converted amount) when conversion isn't possible -
                // silently comparing "3 USD" as if it were "3 AMD" would be
                // actively misleading for sorting/best-price, not just
                // imprecise. A null here means this response is excluded
                // from "best price" and sorts to the end under price sort.
                $cheapestConverted = $cheapest->price_currency === 'AMD'
                    ? (float) $cheapest->price_amount
                    : $currencyConverter->convert((float) $cheapest->price_amount, $cheapest->price_currency, 'AMD');
            }

            return [
                'id' => $response->id,
                'hasReplied' => $response->has_replied,
                'hasDiscount' => $response->has_replied && $response->suggestions->contains(fn ($s) => (bool) $s->promo_code),
                'cheapestPrice' => $cheapestConverted,
                'repliedAt' => $response->responded_at?->timestamp,
            ];
        })
        ->values();

    // The single cheapest reply overall - only meaningful once there are at
    // least 2 responses with an actually comparable (same-currency-basis)
    // price. A currency conversion can fail (missing rate data - see
    // CurrencyConverter), leaving some responses with a null cheapestPrice;
    // singling out "the best" from just one valid price, with nothing real
    // to compare it against, would be as misleading as comparing across
    // currencies directly.
    $comparablePrices = $responseStats->where('hasReplied', true)->whereNotNull('cheapestPrice');
    $best = $comparablePrices->count() >= 2 ? $comparablePrices->sortBy('cheapestPrice')->first() : null;
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
            {{-- Defaults to showing only answered orgs, but only when there's
            at least one - otherwise a page loaded right after submission
            (nobody's replied yet) would show an empty "no matches" state
            instead of the reassuring waiting-for-replies list. --}}
            filterAnswered: {{ $repliedCount > 0 ? 'true' : 'false' }},
            filterDiscount: false,
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
                if (this.filterDiscount && !s.hasDiscount) return false;
                return true;
            },
            orderFor(id) {
                // CSS order needs a plain integer - Infinity isn't a valid
                // value and gets silently dropped, so unpriced items fall
                // back to a large-but-finite number instead.
                const s = this.statFor(id);
                if (this.sortBy === 'price_asc') return s.cheapestPrice ?? 999999;
                if (this.sortBy === 'price_desc') return -(s.cheapestPrice ?? -999999);
                return -(s.repliedAt ?? 0);
            },
            get visibleCount() {
                return this.stats.filter((s) => this.isVisible(s.id)).length;
            },
            get bestSelectedId() {
                const priced = this.stats.filter((s) => this.selected.includes(s.id) && s.cheapestPrice !== null);
                if (priced.length < 2) return null;
                return priced.reduce((best, s) => (s.cheapestPrice < best.cheapestPrice ? s : best)).id;
            },
        }"
    >
        @if (session('status') === 'quote-request-submitted')
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{-- session('contacted_count') is the real, synchronously-known
                     partner match count from the controller - $quoteRequest->responses
                     only exist once SendQuoteRequestToPartnersJob (queued) has
                     actually run, which can lag behind this very first page
                     load by up to a minute (see bootstrap/app.php's scheduled
                     queue:work) - falling back to responses count covers the
                     rare case this flash value isn't set. --}}
                {{ __('tourism.results.submitted', ['count' => session('contacted_count', $quoteRequest->responses->count())]) }}
            </div>
        @endif

        @if (session('status') === 'promo-claimed')
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.results.promo_claimed_status') }}
            </div>
        @endif

        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.results.heading') }}</h1>

        {{-- Trip summary "ticket" --}}
        <div class="mt-6 rounded-2xl border border-placeholder p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">
                    {{ $flags[$quoteRequest->destination_country] ?? '✈️' }}
                </span>
                <div class="min-w-0">
                    <p class="font-heading font-semibold text-ink">
                        {{ __('tourism.results.trip_summary', [
                            'destination' => __('destinations.' . $quoteRequest->destination_country),
                            'check_in' => $quoteRequest->check_in->translatedFormat('d M'),
                            'check_out' => $quoteRequest->check_out->translatedFormat('d M Y'),
                            'adults' => $quoteRequest->adults,
                            'children' => $quoteRequest->children,
                        ]) }}
                    </p>
                    @if ($quoteRequest->hotel_name)
                        <p class="mt-0.5 truncate text-sm text-muted">{{ $quoteRequest->hotel_name }}</p>
                    @endif
                </div>
            </div>

            <p class="mt-3 border-t border-placeholder pt-3 text-xs {{ $quoteRequest->is_open ? 'text-primary' : 'text-subtle' }}">
                @if ($quoteRequest->is_open)
                    {{ __('tourism.results.expires_note', ['date' => $quoteRequest->expires_at->translatedFormat('d F Y'), 'countdown' => $quoteRequest->closes_in]) }}
                @else
                    {{ __('tourism.results.closed_note', ['date' => $quoteRequest->expires_at->translatedFormat('d F Y')]) }}
                @endif
            </p>
        </div>

        @if ($sortedResponses->isNotEmpty())
            {{-- Filter/sort bar - purely client-side, everything needed is
            already embedded in the Alpine state above. --}}
            <div class="mt-6 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    @click="filterAnswered = !filterAnswered"
                    :class="filterAnswered ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                >
                    {{ __('tourism.results.filter_answered_only') }}
                </button>
                <button
                    type="button"
                    @click="filterDiscount = !filterDiscount"
                    :class="filterDiscount ? 'bg-primary text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                >
                    🎁 {{ __('tourism.results.filter_discount_only') }}
                </button>

                <span class="mx-1 h-4 w-px bg-placeholder"></span>

                <select
                    x-model="sortBy"
                    class="rounded-full border border-placeholder bg-white px-3 py-1.5 text-xs font-medium text-ink focus:border-primary focus:outline-none"
                >
                    <option value="recent">{{ __('tourism.results.sort_recent') }}</option>
                    <option value="price_asc">{{ __('tourism.results.sort_price_asc') }}</option>
                    <option value="price_desc">{{ __('tourism.results.sort_price_desc') }}</option>
                </select>
            </div>

            @if ($repliedCount >= 2)
                <p class="mt-3 text-sm text-muted">{{ __('tourism.results.compare_hint') }}</p>
            @endif

            <div x-show="visibleCount === 0" x-cloak class="mt-4 rounded-2xl border border-dashed border-placeholder p-8 text-center">
                <p class="text-sm text-muted">{{ __('tourism.results.no_matches_filtered') }}</p>
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
                                    @php $cheapest = $response->cheapestSuggestion(); @endphp
                                    @if ($cheapest)
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                            <span class="font-heading text-sm font-bold text-primary">
                                                {{ __('tourism.results.from_price', [
                                                    'price' => rtrim(rtrim((string) $cheapest->price_amount, '0'), '.') . ' ' . $cheapest->price_currency,
                                                ]) }}
                                            </span>
                                            @if ($response->id === $bestResponseId)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-accent-yellow/30 px-2 py-0.5 text-xs font-semibold text-ink">
                                                    🏆 {{ __('tourism.results.best_price_badge') }}
                                                </span>
                                            @endif
                                            @if ($response->suggestions->contains(fn ($s) => (bool) $s->promo_code))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-accent-yellow/20 px-2 py-0.5 text-xs font-medium text-ink">
                                                    🎁 {{ __('tourism.results.discount_badge_short') }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            @if ($response->has_replied)
                                <span class="hidden shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary sm:flex">
                                    <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                                    {{ __('tourism.results.replied_label', ['time' => $response->responded_at->diffForHumans()]) }}
                                </span>
                            @elseif ($response->is_declined)
                                <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-subtle">
                                    {{ __('tourism.results.declined_label') }}
                                </span>
                            @else
                                <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-muted">
                                    <span class="h-1.5 w-1.5 motion-safe:animate-pulse rounded-full bg-subtle"></span>
                                    {{ __('tourism.results.waiting_label') }}
                                </span>
                            @endif

                            <svg
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                class="h-5 w-5 shrink-0 text-subtle transition-transform"
                                :class="expanded.includes({{ $response->id }}) ? 'rotate-180' : ''"
                                :aria-label="expanded.includes({{ $response->id }}) ? '{{ __('tourism.results.collapse_hint') }}' : '{{ __('tourism.results.expand_hint') }}'"
                            >
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>

                    <div x-show="expanded.includes({{ $response->id }})" x-cloak x-transition class="border-t border-placeholder px-5 pb-5">
                    @if ($response->has_replied)
                        <p class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-primary sm:hidden">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                            {{ __('tourism.results.replied_label', ['time' => $response->responded_at->diffForHumans()]) }}
                        </p>
                        @if ($response->reply_text)
                            <p class="mt-3 rounded-xl bg-primary/5 px-4 py-3 text-sm leading-relaxed text-ink">{{ $response->reply_text }}</p>
                        @endif

                        <div class="mt-3 space-y-4">
                            @foreach ($response->suggestions as $suggestion)
                                <div class="{{ !$loop->first ? 'border-t border-placeholder pt-4' : '' }}">
                                    @if ($response->suggestions->count() > 1)
                                        <p class="text-xs font-semibold tracking-wide text-subtle uppercase">
                                            {{ str_replace(':number', $loop->iteration, __('tourism.respond.suggestion_label')) }}
                                        </p>
                                    @endif

                                    <p class="mt-1 font-heading text-xl font-bold text-primary">
                                        {{ rtrim(rtrim((string) $suggestion->price_amount, '0'), '.') }} {{ $suggestion->price_currency }}
                                    </p>

                                    @if ($suggestion->price_currency !== $preferredCurrency)
                                        @php
                                            $converted = $currencyConverter->convert((float) $suggestion->price_amount, $suggestion->price_currency, $preferredCurrency);
                                        @endphp
                                        @if ($converted !== null)
                                            <p class="text-xs text-subtle">
                                                {{ __('tourism.results.approx_price', [
                                                    'amount' => number_format($converted, 0),
                                                    'currency' => $preferredCurrency,
                                                ]) }}
                                            </p>
                                        @endif
                                    @endif

                                    <dl class="mt-2 space-y-1 text-sm text-ink">
                                        @if ($suggestion->offered_hotel_name)
                                            <div><dt class="inline text-subtle">{{ __('tourism.results.hotel_label') }}:</dt> <dd class="inline">{{ $suggestion->offered_hotel_name }}</dd></div>
                                        @endif
                                        @if ($suggestion->flight_details)
                                            <div><dt class="inline text-subtle">{{ __('tourism.results.flight_label') }}:</dt> <dd class="inline">{{ $suggestion->flight_details }}</dd></div>
                                        @endif
                                        @if ($suggestion->inclusions)
                                            <div><dt class="inline text-subtle">{{ __('tourism.results.inclusions_label') }}:</dt> <dd class="inline">{{ $suggestion->inclusions }}</dd></div>
                                        @endif
                                    </dl>

                                    @if ($suggestion->attachment_path)
                                        <a href="{{ Storage::url($suggestion->attachment_path) }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-medium text-primary hover:underline">
                                            {{ __('tourism.results.attachment_label') }} &darr;
                                        </a>
                                    @endif

                                    @if ($suggestion->promo_code)
                                        <div class="mt-3 rounded-xl border border-dashed border-primary/40 bg-primary/5 px-4 py-3">
                                            @if ($suggestion->is_claimed)
                                                @if (auth()->check() && auth()->id() === $suggestion->claimed_by_user_id)
                                                    <p class="text-sm font-semibold text-primary">🎁 {{ __('tourism.results.promo_code_label') }}: {{ $suggestion->promo_code }}</p>
                                                    @if ($suggestion->promo_note)
                                                        <p class="mt-1 text-xs text-ink">{{ $suggestion->promo_note }}</p>
                                                    @endif
                                                    <p class="mt-1 text-xs text-subtle">{{ __('tourism.results.promo_claimed_hint') }}</p>
                                                @else
                                                    <p class="text-sm text-subtle">🎁 {{ __('tourism.results.promo_already_claimed') }}</p>
                                                @endif
                                            @else
                                                <p class="text-sm font-semibold text-ink">🎁 {{ __('tourism.results.promo_available') }}</p>
                                                @if ($suggestion->promo_note)
                                                    <p class="mt-1 text-xs text-ink">{{ $suggestion->promo_note }}</p>
                                                @endif

                                                @auth
                                                    <form
                                                        method="POST"
                                                        action="{{ URL::signedRoute('tourism.suggestions.claim', [
                                                            'locale' => app()->getLocale(),
                                                            'quoteRequest' => $quoteRequest->id,
                                                            'suggestion' => $suggestion->id,
                                                        ], $quoteRequest->expires_at) }}"
                                                        class="mt-2"
                                                    >
                                                        @csrf
                                                        <button type="submit" class="text-xs font-medium text-primary hover:underline">
                                                            {{ __('tourism.results.promo_claim_button') }} &rarr;
                                                        </button>
                                                    </form>
                                                @else
                                                    <p class="mt-2 text-xs text-subtle">
                                                        {{ __('tourism.results.promo_login_hint') }}
                                                        <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">{{ __('tourism.results.promo_login_link') }}</a>
                                                        {{ __('tourism.results.promo_or') }}
                                                        <a href="{{ route('register.customer') }}" class="font-medium text-primary hover:underline">{{ __('tourism.results.promo_register_link') }}</a>
                                                    </p>
                                                @endauth
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if ($response->has_contact_info)
                            <div class="mt-3 border-t border-placeholder pt-3">
                                <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('tourism.results.contact_heading') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if ($response->contact_phone)
                                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $response->contact_phone) }}" class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60">
                                            📞 {{ __('tourism.results.contact_call') }}
                                        </a>
                                    @endif
                                    @if ($response->contact_whatsapp)
                                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $response->contact_whatsapp) }}" target="_blank" rel="noopener" class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60">
                                            💬 {{ __('tourism.results.contact_whatsapp') }}
                                        </a>
                                    @endif
                                    @if ($response->contact_telegram)
                                        <a href="https://t.me/{{ ltrim($response->contact_telegram, '@') }}" target="_blank" rel="noopener" class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60">
                                            ✈️ {{ __('tourism.results.contact_telegram') }}
                                        </a>
                                    @endif
                                    @if ($response->contact_instagram)
                                        <a href="https://instagram.com/{{ ltrim($response->contact_instagram, '@') }}" target="_blank" rel="noopener" class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60">
                                            📷 {{ __('tourism.results.contact_instagram') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
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
                                {{ __('tourism.results.add_to_compare') }}
                            </label>
                        @endif
                    @elseif ($response->is_declined)
                        <p class="mt-4 text-sm text-subtle">{{ __('tourism.results.declined_hint') }}</p>
                    @else
                        <p class="mt-4 text-sm text-subtle">{{ __('tourism.results.no_reply_yet') }}</p>
                    @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                    <p class="text-sm text-muted">{{ __('tourism.results.no_responses_yet') }}</p>
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
                <span x-text="selected.length"></span> {{ __('tourism.results.quotes_selected') }}
            </span>
            <div class="flex items-center gap-4">
                <button type="button" @click="selected = []" class="text-xs font-medium text-subtle hover:text-ink">
                    {{ __('tourism.results.compare_bar_clear') }}
                </button>
                <a href="#compare-table" class="bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                    {{ __('tourism.results.compare_bar_button') }}
                </a>
            </div>
        </div>

        <p x-show="selected.length >= 3" x-cloak class="mt-2 text-center text-xs text-subtle">
            {{ __('tourism.results.compare_max_reached') }}
        </p>

        {{-- Side-by-side comparison table --}}
        <div x-show="selected.length >= 2" x-cloak id="compare-table" class="mt-10 scroll-mt-24">
            <h2 class="font-heading text-lg font-semibold text-ink">{{ __('tourism.results.compare_heading') }}</h2>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-placeholder">
                <table class="w-full min-w-[480px] border-collapse text-sm">
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
                                        🏆 {{ __('tourism.results.best_price_badge') }}
                                    </span>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.compare_row_price') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4" :class="item.id === bestSelectedId ? 'bg-accent-yellow/10' : ''">
                                    <span x-show="item.price" class="font-heading text-lg font-bold text-primary" x-text="item.price"></span>
                                    <span x-show="!item.price" class="text-subtle">{{ __('tourism.results.compare_no_price') }}</span>
                                </td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.hotel_label') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4 text-ink" x-text="item.hotel || '—'"></td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.flight_label') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4 text-ink" x-text="item.flight || '—'"></td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.inclusions_label') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4 text-ink" x-text="item.inclusions || '—'"></td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left align-top text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.compare_row_reply') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="max-w-[240px] border-t border-placeholder px-4 py-4 align-top text-sm leading-relaxed text-ink" x-text="item.notes || '—'"></td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-subtle">{{ __('tourism.results.bookmark_hint') }}</p>
    </section>
@endsection
