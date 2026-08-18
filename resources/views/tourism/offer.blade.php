@extends('layouts.app')

@section('title', __('tourism.offer.heading', ['agency' => $organization->name]) . ' — Findex')

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-16 lg:px-10">
        <a href="{{ $offersUrl }}" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path fill-rule="evenodd" d="M12.7 15.7a1 1 0 0 1-1.4 0l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 1 1 1.4 1.4L8.4 10l4.3 4.3a1 1 0 0 1 0 1.4Z" clip-rule="evenodd" />
            </svg>
            {{ __('tourism.offer.back_to_offers') }}
        </a>

        @if (session('status') === 'offer-selected')
            <div class="mt-4 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.offers.selected_confirmation') }}
            </div>
        @endif

        <h1 class="mt-6 font-heading text-2xl font-bold text-ink lg:text-3xl">
            {{ __('tourism.offer.heading', ['agency' => $organization->name]) }}
        </h1>

        @if ($offer->is_expired)
            <div class="mt-4 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm text-ink">
                {{ __('tourism.offer.expired_notice') }}
            </div>
        @endif

        <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_340px] lg:items-start">
            {{-- The offer --}}
            <div class="space-y-6">
                <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                    @if ($badges !== [])
                        <div class="mb-4 flex flex-wrap gap-1.5">
                            @foreach ($badges as $badge)
                                <span class="rounded-full bg-accent-yellow/25 px-2.5 py-1 text-xs font-semibold text-ink">
                                    {{ __('tourism.offers.badge_' . $badge) }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <x-offer-facts :offer="$offer" :request="$quoteRequest" />

                    @if ($offer->attachment_path)
                        <a href="{{ Storage::url($offer->attachment_path) }}" target="_blank" rel="noopener" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">
                            {{ __('tourism.results.attachment_label') }} &darr;
                        </a>
                    @endif
                </div>

                @if ($response->reply_text)
                    <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                        <h2 class="font-heading text-sm font-semibold text-ink">{{ __('tourism.offer.agency_note') }}</h2>
                        <p class="mt-2 text-sm leading-relaxed text-ink">{{ $response->reply_text }}</p>
                    </div>
                @endif

                <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                    <h2 class="font-heading text-sm font-semibold text-ink">{{ __('tourism.request.summary_heading') }}</h2>
                    <div class="mt-4">
                        <x-trip-brief :request="$quoteRequest" />
                    </div>
                </div>
            </div>

            {{-- Price, agency, and the one action --}}
            <div class="space-y-6">
                <div class="rounded-2xl border-2 border-primary/20 bg-primary/5 p-6">
                    <p class="text-xs text-subtle">{{ __('tourism.offers.total_price') }}</p>
                    <p class="font-heading text-3xl font-bold text-ink">
                        {{ rtrim(rtrim((string) $offer->price_amount, '0'), '.') }} {{ $offer->price_currency }}
                    </p>

                    @if ($response->valid_until)
                        <p class="mt-2 text-xs {{ $offer->is_expired ? 'text-subtle' : 'text-accent-red' }}">
                            @if ($offer->is_expired)
                                {{ __('tourism.offers.expired_on', ['date' => $response->valid_until->translatedFormat('d M Y, H:i')]) }}
                            @else
                                {{ __('tourism.offers.valid_until', ['date' => $response->valid_until->translatedFormat('d M Y, H:i')]) }}
                            @endif
                        </p>
                    @endif

                    {{-- The end of Findex's involvement: this records the
                    traveler's choice and tells the agency. No checkout, no
                    payment - the agency books the trip itself. --}}
                    @if ($offer->is_selected)
                        <p class="mt-5 rounded-lg bg-white px-4 py-3 text-center text-sm font-semibold text-primary ring-1 ring-primary/30">
                            {{ __('tourism.offer.chosen') }}
                        </p>
                    @elseif ($offer->is_selectable && $quoteRequest->is_open)
                        <form method="POST" action="{{ $quoteRequest->signedUrlFor('tourism.offers.select', ['suggestion' => $offer->id]) }}" class="mt-5">
                            @csrf
                            <button type="submit" class="w-full bg-primary px-5 py-3 text-sm font-medium text-white transition hover:bg-primary-dark">
                                {{ __('tourism.offer.choose') }}
                            </button>
                        </form>
                        <p class="mt-2 text-center text-xs text-subtle">{{ __('tourism.offer.choose_hint') }}</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                    <h2 class="font-heading text-sm font-semibold text-ink">{{ __('tourism.offer.agency_heading') }}</h2>

                    <div class="mt-4 flex items-center gap-3">
                        @if ($organization->logo)
                            <img src="{{ $organization->logo }}" alt="{{ $organization->name }}" class="h-12 w-12 shrink-0 rounded-full object-contain">
                        @else
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                                {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                            </span>
                        @endif

                        <div class="min-w-0">
                            <p class="truncate font-semibold text-ink">{{ $organization->name }}</p>
                            @if ($organization->reviews_avg_rating)
                                <div class="mt-0.5 flex items-center gap-1.5">
                                    <x-star-rating :rating="$organization->reviews_avg_rating" size="h-3 w-3" />
                                    <span class="text-xs text-subtle">{{ number_format((float) $organization->reviews_avg_rating, 1) }} ({{ $organization->reviews_count }})</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3">
                        <x-organization-badges :organization="$organization" />
                    </div>

                    @if ($organization->description)
                        <p class="mt-4 text-sm leading-relaxed text-muted">{{ $organization->description }}</p>
                    @endif

                    <a href="{{ route('organizations.show', $organization) }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">
                        {{ __('tourism.offer.view_profile') }} &rarr;
                    </a>
                </div>

                {{-- Contact. The agency's per-response details take priority
                over its profile ones: they're what this agency asked to be
                reached on for this particular quote. --}}
                <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                    <h2 class="font-heading text-sm font-semibold text-ink">{{ __('tourism.offer.contact_heading') }}</h2>

                    <x-agency-contact
                        :response="$response"
                        :organization="$organization"
                        :fallback="__('tourism.offer.no_contact_details')"
                        class="mt-3"
                    />

                    <p class="mt-4 text-xs text-subtle">{{ __('tourism.offer.contact_hint') }}</p>
                </div>
            </div>
        </div>
    </section>
@endsection
