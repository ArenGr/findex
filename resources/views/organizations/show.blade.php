@extends('layouts.app')

@section('title', $organization->name . ' — Findex')

@if ($rates['currency_count'] > 0)
    {{-- Named after what the page actually answers, which is what someone
    searching "ACBA exchange rates" is looking for. Only claimed when there are
    rates to back it up. --}}
    @section('description', __('organizations.rates_meta_description', [
        'name' => $organization->name,
        'count' => $rates['currency_count'],
    ]))
@endif

@section('content')
    @php
        // The sticky nav only lists sections the page actually renders -
        // pointing at an anchor that is not there scrolls nowhere.
        $sections = array_filter([
            'overview' => true,
            'exchange-rates' => $organization->hasRatesPage() && $rates['groups'] !== [],
            'branches' => $organization->branches->isNotEmpty(),
            'reviews' => true,
        ]);
    @endphp

    <div class="mx-auto max-w-7xl px-6 py-10 lg:px-10">

        @if (session('status') === 'review-submitted')
            <div class="mb-8 border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('organizations.review_submitted') }}
            </div>
        @endif

        @if (session('status') === 'email-verification-required')
            <div class="mb-8 border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                {{ __('auth.verify_email.action_blocked') }}
            </div>
        @endif

        {{-- Hero. Everything needed to recognise the organization and act on
        it, above the fold: who it is, how it is rated, how big it is, and the
        two things anyone does next. --}}
        <div class="flex flex-col items-center gap-6 rounded-2xl border border-placeholder bg-white p-6 shadow-sm md:flex-row md:items-center md:p-8">
            @if ($organization->logo)
                <img src="{{ $organization->logo }}" alt="{{ $organization->name }}" class="h-24 w-24 shrink-0 rounded-full border border-placeholder bg-white object-contain p-2">
            @else
                <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-2xl font-bold text-white">
                    {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                </div>
            @endif

            <div class="min-w-0 flex-1 text-center md:text-left">
                <div class="flex flex-col items-center gap-x-4 gap-y-1 md:flex-row md:items-baseline">
                    <h1 class="font-heading text-2xl font-bold break-words text-ink lg:text-3xl">{{ $organization->name }}</h1>

                    <div class="flex items-center gap-2 text-sm">
                        @if ($averageRating)
                            <x-star-rating :rating="$averageRating" size="h-4 w-4" />
                            <span class="font-semibold text-ink">{{ number_format($averageRating, 1) }}</span>
                            <span class="text-muted">({{ $reviewsCount }})</span>
                        @else
                            <span class="text-muted">{{ __('organizations.no_rating') }}</span>
                        @endif
                    </div>
                </div>

                {{-- The one-line identity: type, country, and how many places
                you can actually walk into. --}}
                <p class="mt-2 text-sm break-words text-muted">
                    {{ __('organizations.types.' . $organization->type) }}
                    <span aria-hidden="true">·</span>
                    {{ __('organizations.countries.' . $organization->country_code) }}
                    @if ($organization->branches->isNotEmpty())
                        <span aria-hidden="true">·</span>
                        {{ trans_choice('organizations.branch_count', $organization->branches->count(), ['count' => $organization->branches->count()]) }}
                    @endif
                </p>

                <div class="mt-3 flex flex-wrap justify-center gap-2 md:justify-start">
                    <x-organization-badges :organization="$organization" :include-fast-responder="true" />
                </div>
            </div>

            <div class="flex w-full shrink-0 flex-col gap-3 sm:flex-row md:w-auto md:flex-col lg:flex-row">
                <x-compare-toggle :organization="$organization" />

                @if ($organization->website)
                    <a
                        href="{{ $organization->website }}"
                        target="_blank" rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border-muted px-5 py-2.5 text-sm font-semibold break-words text-primary transition hover:border-primary hover:bg-primary/5"
                    >
                        {{ __('organizations.visit_website') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M7 17 17 7M9 7h8v8" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- Section nav. These pages run long once a bank has 56 branches and
        five rate tables, and the rates are usually what someone came for. --}}
        @if (count($sections) > 1)
            <nav
                class="sticky top-0 z-30 -mx-6 mb-10 mt-8 border-b border-placeholder bg-white/95 px-6 backdrop-blur lg:-mx-10 lg:px-10"
                aria-label="{{ $organization->name }}"
            >
                <div class="flex gap-8 overflow-x-auto">
                    @foreach ($sections as $id => $shown)
                        <a
                            href="#{{ $id }}"
                            class="border-b-2 border-transparent py-4 text-xs font-semibold tracking-wider whitespace-nowrap text-muted uppercase transition hover:border-primary hover:text-primary"
                        >
                            {{ __('organizations.nav_' . \Illuminate\Support\Str::of($id)->replace('exchange-rates', 'rates')->toString()) }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif

        {{-- Overview --}}
        <section id="overview" class="mb-16 scroll-mt-20">
            <h2 class="font-heading text-xl font-semibold text-ink">{{ __('organizations.overview_heading') }}</h2>

            <div class="mt-4 rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
        @if ($organization->description)
            <p class="mt-6 text-sm leading-relaxed text-body-text">{{ $organization->description }}</p>
        @endif

        {{--
            The rates themselves, which this page did not show at all - the one
            thing someone searching "<bank> exchange rates" came for, and the
            reason these pages exist as an entry point rather than only as a
            destination from /rates.
        --}}

                <dl class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="flex items-center gap-3 rounded-xl border border-placeholder bg-placeholder/15 p-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-primary">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true"><path d="m12 17.3-6.2 3.7 1.6-7L2 9.2l7.1-.6L12 2l2.9 6.6 7.1.6-5.4 4.8 1.6 7z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <dd class="font-heading text-lg font-semibold text-ink">{{ $averageRating ? number_format($averageRating, 1) : '—' }}</dd>
                            <dt class="text-xs break-words text-muted">{{ __('organizations.stat_rating') }}</dt>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-placeholder bg-placeholder/15 p-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><path d="M3 21h18M4 21V8l8-5 8 5v13M9 21v-6h6v6"/></svg>
                        </span>
                        <div class="min-w-0">
                            <dd class="font-heading text-lg font-semibold text-ink">{{ $organization->branches->isNotEmpty() ? $organization->branches->count() : '—' }}</dd>
                            <dt class="text-xs break-words text-muted">{{ __('organizations.stat_branches') }}</dt>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-placeholder bg-placeholder/15 p-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20"/></svg>
                        </span>
                        <div class="min-w-0">
                            <dd class="font-heading text-lg font-semibold break-words text-ink">{{ __('organizations.countries.' . $organization->country_code) }}</dd>
                            <dt class="text-xs break-words text-muted">{{ __('organizations.stat_region') }}</dt>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-placeholder bg-placeholder/15 p-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><path d="M3 10h18M5 6l7-3 7 3M4 10v10h16V10M9 20v-6h6v6"/></svg>
                        </span>
                        <div class="min-w-0">
                            <dd class="font-heading text-lg font-semibold break-words text-ink">{{ __('organizations.types.' . $organization->type) }}</dd>
                            <dt class="text-xs break-words text-muted">{{ __('organizations.stat_type') }}</dt>
                        </div>
                    </div>
                </dl>

        @if ($organization->has_contact_info)
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($organization->contact_phone)
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $organization->contact_phone) }}" class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60">
                        📞 {{ __('organizations.contact_call') }}
                    </a>
                @endif
                @if ($organization->contact_whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $organization->contact_whatsapp) }}" target="_blank" rel="noopener" class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60">
                        💬 {{ __('organizations.contact_whatsapp') }}
                    </a>
                @endif
                @if ($organization->contact_telegram)
                    <a href="https://t.me/{{ ltrim($organization->contact_telegram, '@') }}" target="_blank" rel="noopener" class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60">
                        ✈️ {{ __('organizations.contact_telegram') }}
                    </a>
                @endif
                @if ($organization->contact_instagram)
                    <a href="https://instagram.com/{{ ltrim($organization->contact_instagram, '@') }}" target="_blank" rel="noopener" class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60">
                        📷 {{ __('organizations.contact_instagram') }}
                    </a>
                @endif
            </div>
        @endif
            </div>
        </section>

        {{-- Exchange rates --}}
        @if ($organization->hasRatesPage())
            <section id="exchange-rates" class="mb-16 scroll-mt-20">
            <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
                <div class="min-w-0">
                    <h2 class="font-heading text-xl font-semibold break-words text-ink">{{ __('organizations.rates_heading') }}</h2>
                    @if ($rates['updated_at'])
                        <p class="mt-1 text-sm text-muted">
                            {{ __('organizations.rates_updated', ['time' => \Illuminate\Support\Carbon::parse($rates['updated_at'])->diffForHumans()]) }}
                        </p>
                    @endif
                </div>

                {{-- Not a "verified" badge: nothing in the schema verifies
                anyone. It says the one thing that is actually true and actually
                useful - this office is reachable, so asking it will reach
                somebody. Same flag the CTA below is gated on. --}}
                @if ($canNegotiate)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/40 bg-primary/10 px-3 py-1 text-xs font-semibold break-words text-ink">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0 text-primary" aria-hidden="true">
                            <path d="m5 13 4 4L19 7" />
                        </svg>
                        {{ __('exchange_quotes.modal.accepts_requests') }}
                    </span>
                @endif
            </div>

            @if ($canNegotiate)
                @php $firstCode = (string) (collect($rates['groups'])->first()[0]['code'] ?? ''); @endphp

                @if ($activeQuoteRequest)
                    {{--
                        One is already running, so the page stops selling the
                        idea and points at it instead. Offering "get a better
                        rate" to somebody who is mid-request is how you end up
                        with two requests and two sets of offers.
                    --}}
                    <div class="mt-6 rounded-2xl border-2 border-primary/40 bg-primary/5 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
                            <div class="min-w-0">
                                <h2 class="font-heading text-base font-bold break-words text-ink">{{ __('exchange_quotes.modal.active_title') }}</h2>
                                <p class="mt-2 font-heading text-xl font-bold break-words text-ink tabular-nums">
                                    {{ number_format($activeQuoteRequest['amount']) }} {{ $activeQuoteRequest['currency'] }}
                                    <span aria-hidden="true" class="text-muted">&rarr;</span>
                                    {{ __('exchange_quotes.request.amd') }}
                                </p>
                                <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm break-words text-muted">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                                            <span class="absolute inline-flex h-full w-full rounded-full bg-primary opacity-75 motion-safe:animate-ping"></span>
                                            <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                                        </span>
                                        {{ __('exchange_quotes.modal.active_waiting') }}
                                    </span>
                                    <span>{{ __('exchange_quotes.modal.active_asked', ['time' => $activeQuoteRequest['asked']]) }}</span>
                                </p>
                            </div>

                            <a
                                href="{{ $activeQuoteRequest['url'] }}"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border-muted bg-white px-5 py-2.5 text-sm font-semibold break-words text-ink transition hover:border-primary"
                            >
                                {{ __('exchange_quotes.modal.view_request') }}
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </a>
                        </div>
                    </div>
                @else
                    {{--
                        The offer only this organization's own page can make,
                        and only when the fan-out would actually reach them. It
                        says what it is for rather than only naming itself: a
                        bare "Get a better rate" button assumes the visitor
                        already knows that is a thing they can do.
                    --}}
                    <div class="mt-6 flex flex-col items-start justify-between gap-4 rounded-2xl border border-placeholder bg-placeholder/20 p-5 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 items-start gap-3">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-5 w-5 shrink-0 text-primary" aria-hidden="true">
                                <path d="M16 7h6v6" /><path d="m22 7-8.5 8.5-5-5L2 17" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold break-words text-ink">{{ __('exchange_quotes.modal.cta_title') }}</p>
                                <p class="mt-1 text-sm leading-relaxed break-words text-muted">{{ __('exchange_quotes.modal.cta_body') }}</p>
                            </div>
                        </div>

                        <div class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:items-end">
                            {{-- Opens the same modal /rates uses; the href is
                            the full page, so it still works with JS off. --}}
                            <a
                                href="{{ route('exchange.request') }}"
                                onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('better-rate-open', { detail: {{ Js::from([
                                    'form' => ['currency_code' => $firstCode, 'rate_field' => 'buy_rate'],
                                    'context' => ['code' => $firstCode, 'organization' => $organization->name],
                                ]) }} }))"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold break-words text-white transition hover:bg-primary-dark"
                            >
                                {{ __('rates.cta_button') }}
                            </a>
                            <span class="inline-flex items-center justify-center gap-1.5 text-xs break-words text-muted sm:justify-end">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0" aria-hidden="true">
                                    <rect width="18" height="11" x="3" y="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                                {{ __('exchange_quotes.modal.anonymous_chip') }}
                            </span>
                        </div>
                    </div>
                @endif
            @endif

            {{-- One table at a time. These groups are alternatives - the cash
            rate and the card rate for the same currency answer different
            questions - and stacking them meant four near-identical tables
            down the page, with the one someone wanted below the fold.

            Same pill tabs as the /rates table (see components/rates-table),
            so the two pages behave alike. With JS off, x-show never runs and
            every panel stays visible, which is exactly the old layout. --}}
            @if ($rates['groups'] !== [])
                @php $firstRateType = array_key_first($rates['groups']); @endphp

                <div class="mt-6" x-data="{ rateTab: @js($firstRateType) }">
                    @if (count($rates['groups']) > 1)
                        <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('organizations.rates_heading') }}">
                            @foreach ($rates['groups'] as $type => $rows)
                                <button
                                    type="button"
                                    role="tab"
                                    id="rate-tab-{{ $type }}"
                                    aria-controls="rate-panel-{{ $type }}"
                                    :aria-selected="rateTab === @js($type) ? 'true' : 'false'"
                                    @click="rateTab = @js($type)"
                                    :class="rateTab === @js($type) ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
                                    class="min-h-11 rounded-full px-4 py-1.5 text-xs font-medium break-words transition"
                                >
                                    {{ __('organizations.rate_types.' . $type) }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @foreach ($rates['groups'] as $type => $rows)
                        <div
                            x-show="rateTab === @js($type)"
                            @if ($type !== $firstRateType) x-cloak @endif
                            role="tabpanel"
                            id="rate-panel-{{ $type }}"
                            aria-labelledby="rate-tab-{{ $type }}"
                        >
                            @if (count($rates['groups']) === 1)
                                <h3 class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('organizations.rate_types.' . $type) }}</h3>
                            @endif

                            <div class="relative mt-2 overflow-x-auto rounded-xl border border-placeholder">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-placeholder bg-placeholder/25 text-xs font-semibold tracking-wider text-muted uppercase">
                                    <th class="px-4 py-3 text-left sm:px-6">{{ __('rates.currency_label') }}</th>
                                    <th class="px-4 py-3 text-right sm:px-6" title="{{ __('rates.buy_hint') }}">{{ __('rates.buy_column') }}</th>
                                    <th class="px-4 py-3 text-right sm:px-6" title="{{ __('rates.sell_hint') }}">{{ __('rates.sell_column') }}</th>
                                    <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('rates.updated_column') }}</th>
                                    <th class="hidden px-4 py-3 sm:table-cell"><span class="sr-only">{{ __('rates.view_all') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr class="border-b border-placeholder last:border-b-0 hover:bg-placeholder/15">
                                        <td class="px-4 py-4 sm:px-6">
                                            <a
                                                href="{{ route('rates.index', ['currency' => $row['code'], 'type' => $type]) }}"
                                                class="flex min-h-11 items-center gap-2 sm:pointer-events-none"
                                                aria-label="{{ __('organizations.rates_see_all', ['code' => $row['code']]) }}"
                                            >
                                                <span aria-hidden="true">{{ \App\Models\Currency::flag($row['code']) }}</span>
                                                <span class="font-medium text-ink">{{ $row['code'] }}</span>
                                                <span class="hidden text-xs break-words text-muted sm:inline">{{ $row['name'] }}</span>
                                            </a>
                                        </td>
                                        <td @class(['px-4 py-4 text-right text-base text-ink tabular-nums sm:px-6', 'font-semibold' => $row['best_buy'], 'font-medium' => ! $row['best_buy']])>
                                            <span class="inline-flex items-center justify-end gap-2">
                                                @if ($row['best_buy'])
                                                    <x-rates.best-chip :label="__('organizations.rates_best_badge')" />
                                                @endif
                                                {{ number_format($row['buy_rate'], 2) }}
                                            </span>
                                        </td>
                                        <td @class(['px-4 py-4 text-right text-base tabular-nums text-accent-red sm:px-6', 'font-semibold' => $row['best_sell'], 'font-medium' => ! $row['best_sell']])>
                                            <span class="inline-flex items-center justify-end gap-2">
                                                @if ($row['best_sell'])
                                                    <x-rates.best-chip :label="__('organizations.rates_best_badge')" />
                                                @endif
                                                {{ number_format($row['sell_rate'], 2) }}
                                            </span>
                                        </td>
                                        <td class="hidden px-4 py-4 text-right text-xs whitespace-nowrap md:table-cell">
                                            @if ($row['scraped_at'])
                                                <x-rates.freshness
                                                    :scraped-at="$row['scraped_at']"
                                                    :stale="\Illuminate\Support\Carbon::parse($row['scraped_at'])->diffInHours(now()) >= 24"
                                                    :changed-at="$row['changed_at']"
                                                />
                                            @endif
                                        </td>
                                        {{-- Out to the comparison, which is the
                                        question this table raises and cannot
                                        answer: is this a good rate? --}}
                                        {{-- Hidden below sm: "See all USD rates" was being
                                        wrapped into 33px of width and rendered one
                                        letter per line. The currency name in the first
                                        column is the link on a phone instead. --}}
                                        <td class="hidden px-4 py-4 text-right sm:table-cell">
                                            <a
                                                href="{{ route('rates.index', ['currency' => $row['code'], 'type' => $type]) }}"
                                                class="inline-flex min-h-11 items-center text-xs font-medium break-words text-primary hover:underline"
                                            >
                                                {{ __('organizations.rates_see_all', ['code' => $row['code']]) }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-muted">{{ __('organizations.rates_none', ['name' => $organization->name]) }}</p>
            @endif

        {{-- One currency's trend, not eleven: enough to see whether this
        organization moves its rates or sits still, with the full picture a
        click away. --}}
        @if ($historySeries !== [])
            <div class="mt-10 rounded-2xl border border-placeholder bg-white p-5 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                    <h2 class="font-heading text-lg font-semibold break-words text-ink">
                        {{ __('rates.history.title', ['code' => $historyCurrency->code]) }}
                    </h2>
                    <a href="{{ route('rates.history', ['currency' => $historyCurrency->code]) }}" class="-my-2 inline-flex min-h-11 items-center py-2 text-sm font-medium break-words text-primary hover:underline">
                        {{ __('rates.history.link') }} &rarr;
                    </a>
                </div>

                <x-rates.history-chart
                    class="mt-4"
                    :series="$historySeries"
                    :lines="[
                        'buy_rate' => ['label' => __('rates.buy_column'), 'color' => 'var(--color-primary)'],
                        'sell_rate' => ['label' => __('rates.sell_column'), 'color' => 'var(--color-accent-red)'],
                    ]"
                    aria-label="{{ __('rates.history.title', ['code' => $historyCurrency->code]) }}"
                />
            </div>
        @endif
            </section>
        @endif

        {{-- Branches. Where you can actually walk in, and whether it is worth
        walking in right now.

        The filtering is client-side on purpose: the whole list is already on
        the page (56 for the largest bank), so searching it needs no round
        trip, and with JS off every branch stays visible rather than the
        controls silently hiding them. --}}
        @if ($organization->branches->isNotEmpty())
            @php
                $branchCities = $organization->branches->pluck('city')->filter()->unique()->sort()->values();
                $branchPreview = 6;
            @endphp

            <section
                id="branches"
                class="mb-16 scroll-mt-20"
                x-data="{
                    search: '',
                    city: '',
                    openNow: false,
                    expanded: false,
                    preview: {{ $branchPreview }},
                    matches(el) {
                        const term = this.search.trim().toLowerCase();
                        if (term && !el.dataset.haystack.includes(term)) return false;
                        if (this.city && el.dataset.city !== this.city) return false;
                        if (this.openNow && el.dataset.open !== 'yes') return false;
                        return true;
                    },
                    refresh() {
                        if (! this.$refs.list) return;
                        let visible = 0;
                        for (const el of this.$refs.list.children) {
                            const ok = this.matches(el);
                            // Greater-than, not less-than: a raw angle
                            // bracket inside an HTML attribute is legal but
                            // reads as the start of a tag to anything that
                            // parses the page roughly. Which is why this
                            // comment does not contain one either.
                            const room = this.expanded || this.preview > visible;
                            el.hidden = ! (ok && room);
                            if (ok) { visible++; }
                        }
                        this.total = visible;
                    },
                    total: {{ $organization->branches->count() }},
                }"
                x-init="$nextTick(() => refresh())"
                x-effect="search, city, openNow, expanded, refresh()"
            >
                <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
                    <div class="min-w-0">
                        <h2 class="font-heading text-xl font-semibold break-words text-ink">{{ __('organizations.branches_heading') }}</h2>
                        <p class="mt-1 text-sm text-muted">
                            {{ trans_choice('organizations.branch_count', $organization->branches->count(), ['count' => $organization->branches->count()]) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 rounded-2xl border border-placeholder bg-white p-4 shadow-sm md:flex-row md:items-center">
                    <label class="relative min-w-0 flex-1">
                        <span class="sr-only">{{ __('organizations.branches_search') }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true">
                            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />
                        </svg>
                        <input
                            type="search"
                            x-model="search"
                            placeholder="{{ __('organizations.branches_search') }}"
                            class="min-h-11 w-full rounded-xl border border-placeholder py-2 pr-3 pl-9 text-sm text-ink placeholder:text-subtle focus:border-primary focus:ring-1 focus:ring-primary"
                        >
                    </label>

                    @if ($branchCities->count() > 1)
                        <label class="min-w-0">
                            <span class="sr-only">{{ __('organizations.branches_all_regions') }}</span>
                            <select x-model="city" class="min-h-11 w-full rounded-xl border border-placeholder py-2 pr-8 pl-3 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary md:w-48">
                                <option value="">{{ __('organizations.branches_all_regions') }}</option>
                                @foreach ($branchCities as $city)
                                    <option value="{{ $city }}">{{ $city }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2">
                        <input type="checkbox" x-model="openNow" class="h-4 w-4 rounded border-border-muted text-primary focus:ring-primary">
                        <span class="text-sm whitespace-nowrap text-ink">{{ __('organizations.branches_open_now') }}</span>
                    </label>
                </div>

                {{-- The overflow branches are hidden server-side so the
                list does not paint at full length and then collapse. That
                would leave them unreachable with JS off, where the Show-all
                button never appears - so this puts them back. --}}
                <noscript>
                    <style>#branches li[hidden] { display: flex !important; }</style>
                </noscript>

                <ul x-ref="list" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($organization->branches as $branch)
                        @php $isOpen = $branch->isOpenAt(); @endphp
                        <li
                            @if ($loop->index >= $branchPreview) hidden @endif
                            data-haystack="{{ Str::lower($branch->name . ' ' . $branch->address . ' ' . $branch->city) }}"
                            data-city="{{ $branch->city }}"
                            data-open="{{ $isOpen ? 'yes' : 'no' }}"
                            class="flex min-w-0 flex-col rounded-xl border border-placeholder bg-white p-4 transition hover:shadow-md"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-heading text-base font-semibold break-words text-primary">{{ $branch->name }}</h3>
                                <x-branch-hours :branch="$branch" />
                            </div>

                            <div class="mt-3 flex-1 space-y-2 text-sm text-muted">
                                @if ($branch->address)
                                    <p class="flex items-start gap-2 break-words">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                                            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" /><circle cx="12" cy="10" r="3" />
                                        </svg>
                                        <span>{{ $branch->address }}@if ($branch->city), {{ $branch->city }}@endif</span>
                                    </p>
                                @endif

                                <x-branch-week :branch="$branch" />
                            </div>

                            @if ($branch->latitude !== null && $branch->longitude !== null)
                                <a
                                    href="https://www.google.com/maps/dir/?api=1&destination={{ $branch->latitude }},{{ $branch->longitude }}"
                                    target="_blank" rel="noopener noreferrer"
                                    class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl border border-placeholder px-4 py-2 text-xs font-semibold break-words text-ink transition hover:border-primary hover:text-primary"
                                >
                                    {{ __('rates.directions') }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <p x-show="total === 0" x-cloak class="mt-6 text-center text-sm text-muted">
                    {{ __('organizations.branches_no_match') }}
                </p>

                @if ($organization->branches->count() > $branchPreview)
                    <div class="mt-6 text-center">
                        <button
                            type="button"
                            x-show="expanded || total > preview"
                            x-cloak
                            @click="expanded = ! expanded"
                            class="inline-flex min-h-11 items-center rounded-xl border border-placeholder px-8 py-3 text-sm font-semibold text-primary transition hover:bg-primary/5"
                        >
                            <span x-show="! expanded">{{ __('organizations.branches_show_all', ['count' => $organization->branches->count()]) }}</span>
                            <span x-show="expanded" x-cloak>{{ __('organizations.branches_show_fewer') }}</span>
                        </button>
                    </div>
                @endif
            </section>
        @endif

        {{-- Reviews --}}
        <section id="reviews" class="mb-16 scroll-mt-20">
        {{-- Reviews --}}
        <h2 class="font-heading text-xl font-semibold text-ink">{{ __('organizations.reviews_heading') }}</h2>

        <form
            method="POST"
            action="{{ route('reviews.store', $organization) }}"
            class="mt-6 max-w-2xl border border-placeholder p-6"
            x-data="{ rating: @js((int) old('rating', $myReview->rating ?? 0)) }"
        >
            @csrf

            {{-- Honeypot: hidden from real visitors, a bot filling every field trips it (see ReviewController::store) --}}
            <div class="hidden" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>

            @guest
                <label for="guest_name" class="block text-sm font-medium text-ink">{{ __('organizations.your_name') }}</label>
                <input
                    type="text"
                    name="guest_name"
                    id="guest_name"
                    value="{{ old('guest_name') }}"
                    placeholder="{{ __('organizations.your_name_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('guest_name') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                >
                @error('guest_name')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            @endguest

            <label class="mt-5 block text-sm font-medium text-ink">{{ __('organizations.your_rating') }}</label>
            <input type="hidden" name="rating" :value="rating">
            <div class="mt-2 flex items-center gap-1">
                @for ($i = 1; $i <= 5; $i++)
                    <button type="button" @click="rating = {{ $i }}" class="focus:outline-none" aria-label="{{ $i }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-8 w-8" :class="rating >= {{ $i }} ? 'fill-accent-yellow' : 'fill-placeholder'">
                            <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                        </svg>
                    </button>
                @endfor
            </div>
            @error('rating')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror

            @if ($organization->branches->isNotEmpty())
                    <label for="branch_id" class="mt-5 block text-sm font-medium text-ink">{{ __('organizations.branch') }}</label>
                    <select
                        name="branch_id"
                        id="branch_id"
                        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                    >
                        <option value="">{{ __('organizations.no_branch') }}</option>
                        @foreach ($organization->branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $myReview->branch_id ?? null) == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <label for="comment" class="mt-5 block text-sm font-medium text-ink">{{ __('organizations.your_comment') }}</label>
                <textarea
                    name="comment"
                    id="comment"
                    rows="4"
                    class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('comment') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                >{{ old('comment', $myReview->comment ?? '') }}</textarea>
                @error('comment')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-5 bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                    {{ $myReview ? __('organizations.update_review') : __('organizations.submit_review') }}
                </button>

                @guest
                    <p class="mt-4 text-xs text-subtle">
                        {{ __('organizations.login_hint') }}
                        <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">{{ __('common.login') }}</a>
                    </p>
                @endguest
        </form>

        <div class="mt-8 max-w-2xl divide-y divide-placeholder border-t border-placeholder">
            @forelse ($organization->reviews as $review)
                <div class="py-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder text-xs font-medium text-ink">
                                {{ Str::of($review->reviewer_name)->substr(0, 1)->upper() }}
                            </span>
                            <span class="text-sm font-medium text-ink">{{ $review->reviewer_name }}</span>
                            @if ($review->user?->email_verified_at)
                                <span class="flex items-center gap-1 text-xs font-medium text-primary" title="{{ __('organizations.verified_reviewer_tooltip') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-4 w-4 fill-primary">
                                        <path fill-rule="evenodd" d="M10 1.8l1.9 1.05 2.16-.2 1.02 1.9 1.9 1.02-.2 2.16 1.05 1.9-1.05 1.9.2 2.16-1.9 1.02-1.02 1.9-2.16-.2L10 18.2l-1.9-1.05-2.16.2-1.02-1.9-1.9-1.02.2-2.16L2.17 10l1.05-1.9-.2-2.16 1.9-1.02 1.02-1.9 2.16.2z" clip-rule="evenodd" />
                                        <path fill="#fff" d="M13.2 7.4l-4 4.4-2.4-2.2-1 1.1 3.4 3.1 5-5.5z" />
                                    </svg>
                                    {{ __('organizations.verified_reviewer') }}
                                </span>
                            @elseif (! $review->user)
                                <span class="rounded-full bg-placeholder/40 px-2 py-0.5 text-xs font-medium text-muted" title="{{ __('organizations.guest_reviewer_tooltip') }}">
                                    {{ __('organizations.guest_reviewer_tag') }}
                                </span>
                            @endif
                        </div>
                        <x-star-rating :rating="$review->rating" />
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-body-text">{{ $review->comment }}</p>
                    <p class="mt-2 text-xs text-subtle">
                        {{ $review->created_at->translatedFormat('d F, Y') }}
                        @if ($review->branch)
                            · {{ $review->branch->name }}
                        @endif
                    </p>

                    @if ($review->reply)
                        <div class="mt-4 ml-4 border-l-2 border-primary/30 pl-4">
                            <p class="text-xs font-semibold text-ink">{{ __('organizations.org_reply_label', ['name' => $organization->name]) }}</p>
                            <p class="mt-1 text-sm leading-relaxed text-body-text">{{ $review->reply->body }}</p>
                        </div>
                    @endif
                </div>
            @empty
                <p class="py-6 text-sm text-muted">{{ __('organizations.no_reviews') }}</p>
            @endforelse
        </div>
        </section>

        {{-- Where to go when this organization is not the answer. --}}
        @if ($similar->isNotEmpty())
            <section class="mb-8">
                <h2 class="font-heading text-xl font-semibold text-ink">{{ __('organizations.similar_heading') }}</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    @foreach ($similar as $peer)
                        @php $peerRate = $similarRates[$peer->id] ?? null; @endphp
                        <div class="flex min-w-0 flex-col items-center rounded-2xl border border-placeholder bg-white p-6 text-center transition hover:shadow-md">
                            @if ($peer->logo)
                                <img src="{{ $peer->logo }}" alt="{{ $peer->name }}" class="mb-4 h-16 w-16 rounded-full border border-placeholder bg-white object-contain p-1">
                            @else
                                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary font-heading text-lg font-bold text-white">
                                    {{ Str::of($peer->name)->substr(0, 2)->upper() }}
                                </div>
                            @endif

                            <h3 class="font-heading text-base font-semibold break-words text-ink">{{ $peer->name }}</h3>

                            <div class="mt-1 mb-4 flex items-center gap-1.5 text-sm text-muted">
                                @if ($peer->reviews_avg_rating)
                                    <x-star-rating :rating="$peer->reviews_avg_rating" size="h-3.5 w-3.5" />
                                    <span class="font-semibold text-ink">{{ number_format($peer->reviews_avg_rating, 1) }}</span>
                                @else
                                    <span>{{ __('organizations.no_rating') }}</span>
                                @endif
                            </div>

                            @if ($peerRate)
                                <div class="mb-4 w-full rounded-xl border border-placeholder bg-placeholder/15 p-3">
                                    <p class="text-xs break-words text-muted">{{ __('organizations.similar_rate_label', ['code' => $headlineCode]) }}</p>
                                    <div class="mt-1 flex items-center justify-between gap-3">
                                        <span class="text-left">
                                            <span class="block text-[10px] tracking-wider text-muted uppercase">{{ __('rates.buy_column') }}</span>
                                            <span class="font-semibold text-ink tabular-nums">{{ number_format($peerRate->buy_rate, 2) }}</span>
                                        </span>
                                        <span class="text-right">
                                            <span class="block text-[10px] tracking-wider text-muted uppercase">{{ __('rates.sell_column') }}</span>
                                            <span class="font-semibold text-accent-red tabular-nums">{{ number_format($peerRate->sell_rate, 2) }}</span>
                                        </span>
                                    </div>
                                </div>
                            @endif

                            <a
                                href="{{ route('organizations.show', $peer) }}"
                                class="mt-auto inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-placeholder px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/5"
                            >
                                {{ __('organizations.similar_view') }}
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @if ($canNegotiate)
        <x-better-rate-modal :currencies="$quoteCurrencies" :cities="$quoteCities" />
    @endif
@endsection
