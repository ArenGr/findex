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
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">

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

        {{-- Header --}}
        <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
            @if ($organization->logo)
                <img src="{{ $organization->logo }}" alt="{{ $organization->name }}" class="h-16 w-16 rounded-full object-contain">
            @else
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-xl font-bold text-white">
                    {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                </div>
            @endif

            <div>
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ $organization->name }}</h1>
                <div class="mt-2 flex items-center gap-3">
                    <x-star-rating :rating="$averageRating ?? 0" size="h-5 w-5" />
                    <span class="text-sm text-muted">
                        {{ $averageRating ? number_format($averageRating, 1) : '—' }}
                        ({{ trans_choice('organizations.reviews_count', $reviewsCount, ['count' => $reviewsCount]) }})
                    </span>
                </div>
                <div class="mt-3">
                    <x-organization-badges :organization="$organization" :include-fast-responder="true" />
                </div>
                <x-compare-toggle :organization="$organization" class="mt-3" />
            </div>
        </div>

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

        {{-- Facts --}}
        <dl class="mt-8 grid grid-cols-2 gap-4 border-y border-placeholder py-6 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.website') }}</dt>
                <dd class="mt-1 text-sm text-ink">
                    <a href="{{ $organization->website }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">
                        {{ $organization->website }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.type') }}</dt>
                <dd class="mt-1 text-sm text-ink">{{ __('organizations.types.' . $organization->type) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.country') }}</dt>
                <dd class="mt-1 text-sm text-ink">{{ __('organizations.countries.' . $organization->country_code) }}</dd>
            </div>
        </dl>

        @if ($organization->description)
            <p class="mt-6 text-sm leading-relaxed text-body-text">{{ $organization->description }}</p>
        @endif

        {{--
            The rates themselves, which this page did not show at all - the one
            thing someone searching "<bank> exchange rates" came for, and the
            reason these pages exist as an entry point rather than only as a
            destination from /rates.
        --}}
        @if ($organization->hasRatesPage())
            <div class="mt-12 flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
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

            @forelse ($rates['groups'] as $type => $rows)
                <div class="mt-6">
                    <h3 class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('organizations.rate_types.' . $type) }}</h3>

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
            @empty
                <p class="mt-4 text-sm text-muted">{{ __('organizations.rates_none', ['name' => $organization->name]) }}</p>
            @endforelse
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

        {{-- Where you can actually walk in, and whether it is worth walking
        in right now. The page had the branches all along - only the review
        form used them, as a dropdown. --}}
        @if ($organization->branches->isNotEmpty())
            <h2 class="mt-12 font-heading text-xl font-semibold break-words text-ink">{{ __('organizations.branches_heading') }}</h2>

            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($organization->branches as $branch)
                    <li class="flex min-w-0 flex-col gap-1 rounded-xl border border-placeholder p-4">
                        <p class="font-medium break-words text-ink">{{ $branch->name }}</p>

                        @if ($branch->address)
                            <p class="text-sm break-words text-muted">{{ $branch->address }}@if ($branch->city), {{ $branch->city }}@endif</p>
                        @elseif ($branch->city)
                            <p class="text-sm break-words text-muted">{{ $branch->city }}</p>
                        @endif

                        <x-branch-hours :branch="$branch" />

                        @if ($branch->latitude !== null && $branch->longitude !== null)
                            <a
                                href="https://www.google.com/maps/dir/?api=1&destination={{ $branch->latitude }},{{ $branch->longitude }}"
                                target="_blank" rel="noopener noreferrer"
                                class="-mb-1 mt-1 inline-flex min-h-11 items-center gap-1 text-xs font-medium break-words text-primary hover:underline"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 shrink-0" aria-hidden="true">
                                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                {{ __('rates.directions') }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Reviews --}}
        <h2 class="mt-12 font-heading text-xl font-semibold text-ink">{{ __('organizations.reviews_heading') }}</h2>

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

    @if ($canNegotiate)
        <x-better-rate-modal :currencies="$quoteCurrencies" :cities="$quoteCities" />
    @endif
@endsection
