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

                {{-- The offer only this organization's own page can make, and
                only when the fan-out job would actually reach them. --}}
                @if ($canNegotiate)
                    <a
                        href="{{ route('exchange.request') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium break-words text-white transition hover:bg-primary-dark"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0" aria-hidden="true">
                            <path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z" />
                            <path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1" />
                        </svg>
                        {{ __('rates.cta_button') }}
                    </a>
                @endif
            </div>

            @forelse ($rates['groups'] as $type => $rows)
                <div class="mt-6">
                    <h3 class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('organizations.rate_types.' . $type) }}</h3>

                    <div class="relative mt-2 overflow-x-auto rounded-xl border border-placeholder">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-placeholder bg-placeholder/25 text-xs font-semibold tracking-wider text-muted uppercase">
                                    <th class="px-6 py-3 text-left">{{ __('rates.currency_label') }}</th>
                                    <th class="px-6 py-3 text-right" title="{{ __('rates.buy_hint') }}">{{ __('rates.buy_column') }}</th>
                                    <th class="px-6 py-3 text-right" title="{{ __('rates.sell_hint') }}">{{ __('rates.sell_column') }}</th>
                                    <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('rates.updated_column') }}</th>
                                    <th class="px-4 py-3"><span class="sr-only">{{ __('rates.view_all') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr class="border-b border-placeholder last:border-b-0 hover:bg-placeholder/15">
                                        <td class="px-6 py-4">
                                            <span class="flex items-center gap-2">
                                                <span aria-hidden="true">{{ \App\Models\Currency::flag($row['code']) }}</span>
                                                <span class="font-medium text-ink">{{ $row['code'] }}</span>
                                                <span class="hidden text-xs break-words text-muted sm:inline">{{ $row['name'] }}</span>
                                            </span>
                                        </td>
                                        <td @class(['px-6 py-4 text-right text-base text-ink tabular-nums', 'font-semibold' => $row['best_buy'], 'font-medium' => ! $row['best_buy']])>
                                            <span class="inline-flex items-center justify-end gap-2">
                                                @if ($row['best_buy'])
                                                    <x-rates.best-chip :label="__('organizations.rates_best_badge')" />
                                                @endif
                                                {{ number_format($row['buy_rate'], 2) }}
                                            </span>
                                        </td>
                                        <td @class(['px-6 py-4 text-right text-base tabular-nums text-accent-red', 'font-semibold' => $row['best_sell'], 'font-medium' => ! $row['best_sell']])>
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
                                        <td class="px-4 py-4 text-right">
                                            <a
                                                href="{{ route('rates.index', ['currency' => $row['code'], 'type' => $type]) }}"
                                                class="text-xs font-medium break-words text-primary hover:underline"
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
                                class="mt-1 inline-flex items-center gap-1 text-xs font-medium break-words text-primary hover:underline"
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
@endsection
