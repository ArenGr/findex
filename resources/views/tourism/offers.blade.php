@extends('layouts.app')

@section('title', trans_choice('tourism.offers.heading', $quoteRequest->offers->count(), ['count' => $quoteRequest->offers->count()]) . ' — Findex')

@php
    use App\Services\TravelOfferComparison;

    $rows = app(TravelOfferComparison::class)->for($quoteRequest);

    // Agencies that were contacted but haven't priced anything yet. Listed
    // separately and plainly, below the real offers - a traveler comparing
    // quotes shouldn't have to scroll past agencies that haven't sent one.
    $pending = $quoteRequest->responses->where('has_replied', false)->values();

    $comparableCount = $rows->count();
@endphp

@section('content')
    <section
        class="mx-auto max-w-6xl px-6 py-16 lg:px-10"
        x-data="{
            selected: [],
            max: {{ \App\Http\Controllers\QuoteRequestController::MAX_COMPARED_OFFERS }},
            toggle(id) {
                this.selected = this.selected.includes(id)
                    ? this.selected.filter((x) => x !== id)
                    : [...this.selected, id];
            },
            get compareUrl() {
                return @js($compareUrl) + (@js($compareUrl).includes('?') ? '&' : '?') + 'offers=' + this.selected.join(',');
            },
        }"
    >
        <a href="{{ $statusUrl }}" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path fill-rule="evenodd" d="M12.7 15.7a1 1 0 0 1-1.4 0l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 1 1 1.4 1.4L8.4 10l4.3 4.3a1 1 0 0 1 0 1.4Z" clip-rule="evenodd" />
            </svg>
            {{ __('tourism.offers.back_to_status') }}
        </a>

        @if (session('status') === 'offer-selected')
            <div class="mt-4 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.offers.selected_confirmation') }}
            </div>
        @endif

        @if (session('status') === 'promo-claimed')
            <div class="mt-4 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.results.promo_claimed_status') }}
            </div>
        @endif

        <h1 class="mt-6 font-heading text-2xl font-bold text-ink lg:text-3xl">
            {{ trans_choice('tourism.offers.heading', $comparableCount, ['count' => $comparableCount]) }}
        </h1>

        <div class="mt-6 rounded-2xl border border-placeholder bg-white p-5 shadow-sm">
            <x-trip-brief :request="$quoteRequest" compact />
        </div>

        @if ($comparableCount >= 2)
            <p class="mt-6 text-sm text-muted">{{ __('tourism.offers.compare_hint', ['max' => \App\Http\Controllers\QuoteRequestController::MAX_COMPARED_OFFERS]) }}</p>
        @endif

        {{-- Offer cards --}}
        <div class="mt-4 space-y-4">
            @forelse ($rows as $row)
                @php
                    $offer = $row['offer'];
                    $response = $row['response'];
                    $organization = $row['organization'];
                    $detailUrl = $quoteRequest->signedUrlFor('tourism.offers.show', ['suggestion' => $offer->id]);
                @endphp

                <article
                    class="rounded-2xl border bg-white shadow-sm transition {{ $offer->is_expired ? 'border-placeholder opacity-70' : 'border-placeholder' }}"
                    :class="selected.includes({{ $offer->id }}) ? 'border-primary ring-2 ring-primary/20' : ''"
                >
                    <div class="flex flex-col gap-5 p-5 lg:flex-row lg:gap-6">
                        {{-- Agency --}}
                        <div class="flex flex-col gap-3 border-placeholder pb-5 lg:w-1/4 lg:border-r lg:border-b-0 lg:pr-5 lg:pb-0 {{ $loop->last ? '' : '' }} border-b">
                            <div class="flex items-center gap-3">
                                @if ($organization->logo)
                                    <img src="{{ $organization->logo }}" alt="{{ $organization->name }}" class="h-11 w-11 shrink-0 rounded-full object-contain">
                                @else
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                                        {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                                    </span>
                                @endif

                                <div class="min-w-0">
                                    <a href="{{ route('organizations.show', $organization) }}" class="block truncate font-semibold text-ink hover:text-primary">
                                        {{ $organization->name }}
                                    </a>
                                    @if ($organization->reviews_avg_rating)
                                        <div class="mt-0.5 flex items-center gap-1.5">
                                            <x-star-rating :rating="$organization->reviews_avg_rating" size="h-3 w-3" />
                                            <span class="text-xs text-subtle">{{ number_format((float) $organization->reviews_avg_rating, 1) }} ({{ $organization->reviews_count }})</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <x-organization-badges :organization="$organization" />

                            @if ($response->reply_text)
                                <p class="rounded-xl bg-placeholder/20 px-3 py-2 text-sm leading-relaxed text-ink">{{ $response->reply_text }}</p>
                            @endif

                            @if ($response->valid_until)
                                <p class="mt-auto text-xs font-medium {{ $offer->is_expired ? 'text-subtle' : 'text-accent-red' }}">
                                    @if ($offer->is_expired)
                                        {{ __('tourism.offers.expired_on', ['date' => $response->valid_until->translatedFormat('d M, H:i')]) }}
                                    @else
                                        {{ __('tourism.offers.valid_until', ['date' => $response->valid_until->translatedFormat('d M, H:i')]) }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        {{-- What's in it --}}
                        <div class="flex-1 border-b border-placeholder pb-5 lg:border-r lg:border-b-0 lg:pr-6 lg:pb-0">
                            <x-offer-facts :offer="$offer" :request="$quoteRequest" />

                            @if ($row['badges'] !== [])
                                <div class="mt-4 flex flex-wrap gap-1.5">
                                    @foreach ($row['badges'] as $badge)
                                        <span class="rounded-full bg-accent-yellow/25 px-2.5 py-1 text-xs font-semibold text-ink">
                                            {{ __('tourism.offers.badge_' . $badge) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Price and actions --}}
                        <div class="flex flex-col justify-between gap-4 lg:w-56">
                            <div>
                                @if ($offer->is_expired)
                                    <span class="mb-2 inline-block rounded-full bg-placeholder/50 px-2.5 py-1 text-xs font-semibold text-subtle">
                                        {{ __('tourism.offers.expired_badge') }}
                                    </span>
                                @elseif ($offer->is_selected)
                                    <span class="mb-2 inline-block rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                        {{ __('tourism.offers.selected_badge') }}
                                    </span>
                                @endif

                                <p class="text-xs text-subtle">{{ __('tourism.offers.total_price') }}</p>
                                <p class="font-heading text-xl font-bold text-ink">
                                    {{ rtrim(rtrim((string) $offer->price_amount, '0'), '.') }} {{ $offer->price_currency }}
                                </p>

                                @if ($offer->price_currency !== $preferredCurrency)
                                    @php
                                        $converted = $currencyConverter->convert((float) $offer->price_amount, $offer->price_currency, $preferredCurrency);
                                    @endphp
                                    @if ($converted !== null)
                                        <p class="text-xs text-subtle">
                                            {{ __('tourism.results.approx_price', ['amount' => number_format($converted, 0), 'currency' => $preferredCurrency]) }}
                                        </p>
                                    @endif
                                @endif
                            </div>

                            <div class="flex flex-col gap-2">
                                <a href="{{ $detailUrl }}" class="bg-primary px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-primary-dark">
                                    {{ __('tourism.offers.view_details') }}
                                </a>

                                {{-- Contacting the agency is the whole point
                                of the platform, so it stays one tap away on
                                the card rather than only on the detail page. --}}
                                @if ($response->has_contact_info)
                                    <p class="text-xs tracking-wide text-subtle uppercase">{{ __('tourism.results.contact_heading') }}</p>
                                    <x-agency-contact :response="$response" :organization="$organization" />
                                @endif

                                @if ($comparableCount >= 2)
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-ink">
                                        <input
                                            type="checkbox"
                                            @change="toggle({{ $offer->id }})"
                                            :checked="selected.includes({{ $offer->id }})"
                                            :disabled="!selected.includes({{ $offer->id }}) && selected.length >= max"
                                            class="rounded border-border-muted text-primary focus:ring-primary disabled:opacity-40"
                                        >
                                        {{ __('tourism.offers.add_to_compare') }}
                                    </label>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                {{-- No offers yet. Which of the two honest reasons applies
                depends on whether anyone was contacted at all. --}}
                <div class="rounded-2xl border border-dashed border-placeholder p-10 text-center">
                    @if ($pending->isEmpty())
                        <p class="text-sm text-muted">{{ __('tourism.offers.empty_no_agencies') }}</p>
                        <a href="{{ route('tourism.request') }}" class="mt-4 inline-block bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
                            {{ __('tourism.mine.new_request') }}
                        </a>
                    @else
                        <p class="text-sm text-muted">{{ __('tourism.offers.empty_waiting', ['count' => $pending->count()]) }}</p>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- Agencies still to answer --}}
        @if ($pending->isNotEmpty() && $rows->isNotEmpty())
            <div class="mt-8 rounded-2xl border border-placeholder bg-white p-5">
                <h2 class="font-heading text-sm font-semibold text-ink">{{ __('tourism.offers.pending_heading') }}</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($pending as $response)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate text-ink">{{ $response->organization->name }}</span>
                            <span class="shrink-0 text-xs {{ $response->is_declined ? 'text-subtle' : 'text-muted' }}">
                                @if ($response->is_declined)
                                    {{ __('tourism.results.declined_label') }}
                                @elseif ($response->is_reviewing)
                                    {{ __('tourism.offers.reviewing_label') }}
                                @else
                                    {{ __('tourism.results.waiting_label') }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Sticky compare bar --}}
        <div
            x-show="selected.length >= 2"
            x-cloak
            x-transition
            class="sticky bottom-4 mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-primary/30 bg-white p-4 shadow-lg"
        >
            <span class="text-sm font-medium text-ink">
                <span x-text="selected.length"></span> {{ __('tourism.offers.selected_to_compare') }}
            </span>
            <div class="flex items-center gap-4">
                <button type="button" @click="selected = []" class="text-xs font-medium text-subtle hover:text-ink">
                    {{ __('tourism.results.compare_bar_clear') }}
                </button>
                <a :href="compareUrl" class="bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                    {{ __('tourism.results.compare_bar_button') }}
                </a>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-subtle">{{ __('tourism.results.bookmark_hint') }}</p>
    </section>
@endsection
