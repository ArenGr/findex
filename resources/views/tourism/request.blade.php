@extends('layouts.app')

@section('title', __('tourism.request.heading') . ' — Findex')

{{-- This page sets font-manrope on its wrapper, so Manrope is its body face at
     every weight. Preloaded here rather than in the layout: no other page uses
     it, and it is ~56 KB. Without it the whole page paints in the fallback and
     then re-renders once Manrope lands. --}}
@push('head')
    @foreach (App\Support\FontPreloads::urls('manrope', app()->getLocale()) as $href)
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ $href }}">
    @endforeach
@endpush

@php
    use App\Models\QuoteRequest;

    // `$fieldIcon` is the same field with room for a leading glyph.
    $field = 'w-full rounded-lg border border-border-subtle bg-white px-4 py-3 text-body-sm text-on-surface transition-colors focus:border-travel-primary focus:ring-1 focus:ring-travel-primary focus:outline-none';
    $fieldIcon = $field.' pl-10';
    $label = 'block text-body-sm text-ink-muted';
    $card = 'rounded-[15px] border border-border-subtle bg-white p-5 shadow-[0_3px_14px_rgba(24,29,18,0.035)]';
    $stepper = 'flex h-9 w-9 items-center justify-center rounded-full border border-border-subtle text-on-surface transition-colors hover:border-travel-primary disabled:opacity-40 disabled:hover:border-border-subtle';
    $sectionIcon = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-travel-primary/10';

    // Which wizard step a failed submission should reopen: the earliest step
    // holding a rejected field. It is all one form and one POST - these
    // groupings only decide what is on screen, never what is validated.
    $stepFields = [
        1 => ['departure_location', 'destination_countries', 'check_in', 'check_out', 'date_flexibility', 'adults', 'children', 'child_ages', 'hotel_name'],
        2 => ['flight_preference', 'hotel_preference', 'meal_preference', 'priorities', 'budget_band', 'budget_min_amd', 'budget_max_amd', 'notes', 'insurance'],
        3 => ['guest_name', 'guest_email', 'consent'],
    ];
    $errorKeys = collect($errors->keys());
    $initialStep = 1;
    foreach ($stepFields as $stepNumber => $prefixes) {
        if ($errorKeys->contains(fn ($key) => collect($prefixes)->contains(fn ($prefix) => str_starts_with($key, $prefix)))) {
            $initialStep = $stepNumber;
            break;
        }
    }

    // The shared button, not a travel-only copy of it: bg-travel-primary is
    // the same #607E34 as the brand green, so this was the same button under
    // another name, drifting on its own.
    $navPrimary = 'btn btn-primary';
    $navGhost = 'btn btn-secondary';
@endphp

@section('content')
    <div
        class="font-manrope text-on-surface"
        x-data="travelRequestForm(@js([
            'countries' => $countries,
            'initialStep' => $initialStep,
            'consented' => (bool) old('consent'),
            'departure' => old('departure_location', ''),
            'destinations' => array_values((array) old('destination_countries', [])),
            'openToSuggestions' => (bool) old('open_to_suggestions'),
            'maxDestinations' => $maxDestinations,
            'checkIn' => old('check_in', ''),
            'checkOut' => old('check_out', ''),
            'dateFlexibility' => old('date_flexibility', ''),
            'adults' => (int) old('adults', 2),
            'children' => (int) old('children', 0),
            'childAges' => array_values((array) old('child_ages', [])),
            'maxChildren' => $maxChildren,
            'maxChildAge' => $maxChildAge,
            'flightPreference' => old('flight_preference', QuoteRequest::FLIGHT_FLEXIBLE),
            'hotelPreference' => old('hotel_preference', QuoteRequest::HOTEL_ANY),
            'mealPreference' => old('meal_preference', QuoteRequest::MEAL_ANY),
            'priorities' => array_values((array) old('priorities', [])),
            'maxPriorities' => $maxPriorities,
            'insurance' => (bool) old('insurance'),
            'budgetBand' => old('budget_band', ''),
            'budgetMin' => old('budget_min_amd', ''),
            'budgetMax' => old('budget_max_amd', ''),
            'labels' => [
                'flight' => $flightOptions,
                'hotel' => $hotelOptions,
                'meals' => $mealOptions,
                'budget' => $budgetBandLabels,
                'notSet' => __('tourism.request.summary_not_set'),
                'openToSuggestions' => __('tourism.request.summary_open_to_suggestions'),
                'adults' => __('tourism.request.adults'),
                'children' => __('tourism.request.children'),
                'nights' => __('tourism.request.summary_nights'),
            ],
        ]))"
    >
        {{-- Hero geometry lives in x-page-hero, shared by every main page. tuck
             leaves room for the stepper card to sit in the hero's bottom edge. --}}
        <x-page-hero
            x-show="step === 1"
            :x-cloak="$initialStep !== 1"
            :title="__('tourism.request.heading')"
            :subtitle="__('tourism.request.subheading')"
        >
            <x-slot:eyebrow>
                <x-hero-badge>
                    <x-slot:icon><x-travel-icon name="flight_takeoff" class="h-4 w-4" /></x-slot:icon>
                    {{ __('tourism.request.eyebrow') }}
                </x-hero-badge>
            </x-slot:eyebrow>

                    <ul class="flex flex-wrap gap-x-6 gap-y-2">
                        <li class="inline-flex items-center gap-1.5 text-body-sm text-ink-muted">
                            <svg class="h-4 w-4 shrink-0 text-travel-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            {{ __('tourism.request.benefit_trusted') }}
                        </li>
                        <li class="inline-flex items-center gap-1.5 text-body-sm text-ink-muted">
                            <svg class="h-4 w-4 shrink-0 text-travel-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 12 22l-8-8 8.6-8.6a2 2 0 0 1 1.4-.6H20a2 2 0 0 1 2 2v5.4a2 2 0 0 1-.6 1.4z"/><circle cx="16.5" cy="7.5" r="1"/></svg>
                            {{ __('tourism.request.benefit_value') }}
                        </li>
                        <li class="inline-flex items-center gap-1.5 text-body-sm text-ink-muted">
                            <svg class="h-4 w-4 shrink-0 text-travel-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            {{ __('tourism.request.benefit_time') }}
                        </li>
                    </ul>

            {{-- Warm-yellow-and-green travel scene: this page's identity, now
                 that the tint is shared. --}}
            {{-- Where you are in the flow, inside the hero. Alpine-bound rather
             than the shared x-hero-steps: this one is clickable and its state
             changes without a page load. Same classes, so the two request
             pages read as the same indicator. --}}
        <x-slot:steps>
            @include('tourism.request._stepper')
        </x-slot:steps>

        <x-slot:illustration>
                    {{-- See the insurance request hero: 1.7 MB PNG replaced by a
                         WebP at twice the size it is ever painted. --}}
                    <img
                        src="{{ asset('images/travel/hero-travel.webp') }}?v={{ filemtime(public_path('images/travel/hero-travel.webp')) }}"
                        alt=""
                        width="960"
                        height="320"
                    >
            </x-slot:illustration>
        </x-page-hero>

        <section x-show="step > 1" @if ($initialStep <= 1) x-cloak @endif class="border-b border-placeholder bg-primary/5">
            <div class="site-container py-6">
                <div class="flex items-center justify-between gap-4">
                    <h1 class="text-headline-md text-on-surface">{{ __('tourism.request.heading') }}</h1>
                    <span class="shrink-0 text-body-sm text-ink-muted" x-text="@js(__('tourism.request.wizard_step_of', ['current' => ':c', 'total' => ':t'])).replace(':c', step).replace(':t', totalSteps)"></span>
                </div>

                <div class="mt-5 border-t border-primary/15 pt-5">
                    @include('tourism.request._stepper')
                </div>
            </div>
        </section>

        <section class="site-container pb-16 lg:pb-20">
            <div id="travel-form-top" class="scroll-mt-4"></div>
            @if (session('status') === 'destination-alert-created')
                <div class="mb-6 rounded-lg border border-travel-primary/30 bg-travel-primary/5 px-4 py-3 text-body-sm text-travel-primary">
                    {{ __('tourism.request.notify_me_confirmed') }}
                </div>
            @endif

            @if (session('status') === 'email-verification-required')
                <div class="mb-6 rounded-lg border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-body-sm text-on-surface">
                    {{ __('auth.verify_email.action_blocked') }}
                </div>
            @endif

            <form method="POST" action="{{ route('tourism.request.store') }}" novalidate>
                @csrf

                <div class="hidden" aria-hidden="true">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-[minmax(0,1fr)_280px]">
                    <div class="flex flex-col gap-6">
                        {{-- STEP 1 --}}
                        {{-- x-cloak only on the steps this render is not
                             starting on. Cloaking all three left the form
                             blank until Alpine booted, so the page painted
                             empty and then filled in. --}}
                        <div data-step="1" x-show="step === 1" @if ($initialStep !== 1) x-cloak @endif class="flex flex-col gap-6">
                            <div>
                                <h2 class="text-headline-md text-on-surface">{{ __('tourism.request.step1_heading') }}</h2>
                                <p class="mt-1 text-body-md text-ink-muted">{{ __('tourism.request.step1_sub') }}</p>
                            </div>

                            @include('tourism.request._voice-fill')
                            @include('tourism.request._trip-details')

                            <div class="flex items-center justify-end">
                                <button type="button" @click="next()" class="{{ $navPrimary }}">
                                    {{ __('tourism.request.wizard_continue_prefs') }}
                                    <x-travel-icon name="arrow_forward" class="h-[18px] w-[18px]" />
                                </button>
                            </div>
                        </div>

                        {{-- STEP 2 --}}
                        <div data-step="2" x-show="step === 2" @if ($initialStep !== 2) x-cloak @endif class="flex flex-col gap-6">
                            <div>
                                <h2 class="text-headline-md text-on-surface">{{ __('tourism.request.step2_heading') }}</h2>
                                <p class="mt-1 text-body-md text-ink-muted">{{ __('tourism.request.step2_sub') }}</p>
                            </div>

                            @include('tourism.request._preferences')
                            @include('tourism.request._priorities')
                            @include('tourism.request._budget-notes')

                            <div class="flex items-center justify-between gap-3">
                                <button type="button" @click="back()" class="{{ $navGhost }}">
                                    <x-travel-icon name="arrow_back" class="h-[18px] w-[18px]" />
                                    {{ __('tourism.request.wizard_back') }}
                                </button>
                                <button type="button" @click="next()" class="{{ $navPrimary }}">
                                    {{ __('tourism.request.wizard_continue') }}
                                    <x-travel-icon name="arrow_forward" class="h-[18px] w-[18px]" />
                                </button>
                            </div>
                        </div>

                        {{-- STEP 3 --}}
                        <div data-step="3" x-show="step === 3" @if ($initialStep !== 3) x-cloak @endif class="flex flex-col gap-6">
                            <div>
                                <h2 class="text-headline-md text-on-surface">{{ __('tourism.request.step3_heading') }}</h2>
                                <p class="mt-1 text-body-md text-ink-muted">{{ __('tourism.request.step3_sub') }}</p>
                            </div>

                            <section class="{{ $card }}">
                                <div class="mb-4 flex items-center justify-between gap-3">
                                    <h3 class="text-headline-sm font-semibold text-on-surface">{{ __('tourism.request.review_heading') }}</h3>
                                    <button type="button" @click="goToStep(1)" class="text-body-sm font-medium text-travel-primary hover:underline">{{ __('tourism.request.summary_edit') }}</button>
                                </div>

                                <div x-show="hasItinerary" x-cloak class="mb-4 border-b border-border-subtle pb-4">
                                    <p class="text-body-lg font-semibold text-on-surface" x-text="itineraryRoute"></p>
                                    <p class="mt-1 text-body-sm text-ink-muted" x-text="itineraryMeta"></p>
                                </div>

                                <dl class="flex flex-col gap-2.5">
                                    @foreach ([
                                        ['label' => __('tourism.request.summary_flight'), 'value' => 'flightSummary'],
                                        ['label' => __('tourism.request.summary_hotel'), 'value' => 'hotelSummary'],
                                        ['label' => __('tourism.request.summary_meals'), 'value' => 'mealsSummary'],
                                        ['label' => __('tourism.request.summary_budget'), 'value' => 'budgetSummary'],
                                    ] as $row)
                                        <div class="flex justify-between gap-3 text-body-sm">
                                            <dt class="shrink-0 text-ink-muted">{{ $row['label'] }}</dt>
                                            <dd class="text-right font-semibold text-on-surface" x-text="{{ $row['value'] }}"></dd>
                                        </div>
                                    @endforeach
                                    <div x-show="priorities.length" x-cloak class="flex justify-between gap-3 border-t border-border-subtle pt-2.5 text-body-sm">
                                        <dt class="shrink-0 text-ink-muted">{{ __('tourism.request.priorities_label') }}</dt>
                                        <dd class="flex flex-wrap justify-end gap-1.5">
                                            <template x-for="value in priorities" :key="value">
                                                <span class="rounded-full bg-travel-primary/10 px-2.5 py-1 text-label-caps text-travel-primary" x-text="@js($priorityOptions)[value]"></span>
                                            </template>
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="{{ $card }}">
                                <div class="mb-5 flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-travel-primary/10">
                                        <x-travel-icon name="group" class="h-[18px] w-[18px] text-travel-primary" />
                                    </span>
                                    <h3 class="text-headline-md">{{ __('tourism.request.section_details') }}</h3>
                                </div>

                                @guest
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="flex flex-col gap-1">
                                            <label for="guest_name" class="{{ $label }}">{{ __('tourism.request.your_name') }}</label>
                                            <input type="text" name="guest_name" id="guest_name" value="{{ old('guest_name') }}" required class="{{ $field }} @error('guest_name') border-error @enderror">
                                            @error('guest_name')
                                                <p class="text-body-sm text-error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            <label for="guest_email" class="{{ $label }}">{{ __('tourism.request.your_email') }}</label>
                                            <input type="email" name="guest_email" id="guest_email" value="{{ old('guest_email') }}" required class="{{ $field }} @error('guest_email') border-error @enderror">
                                            @error('guest_email')
                                                <p class="text-body-sm text-error">{{ $message }}</p>
                                            @enderror
                                            <p class="text-body-sm text-ink-muted">{{ __('tourism.request.your_email_hint') }}</p>
                                        </div>
                                    </div>
                                @endguest

                                @auth
                                    <p class="text-body-sm text-ink-muted">
                                        {{ __('tourism.request.your_email_hint') }}
                                        <span class="font-medium text-on-surface">{{ auth()->user()->email }}</span>
                                    </p>
                                @endauth
                            </section>

                            <section class="{{ $card }}">
                                <label class="flex cursor-pointer items-start gap-2 text-body-sm text-on-surface">
                                    <input type="checkbox" name="consent" value="1" x-model="consented" class="mt-0.5 h-4 w-4 shrink-0 rounded border-border-subtle text-travel-primary focus:ring-travel-primary">
                                    <span>{{ __('tourism.request.consent') }}</span>
                                </label>
                                @error('consent')
                                    <p class="mt-2 text-body-sm text-error">{{ $message }}</p>
                                @enderror

                                <div class="mt-5 flex items-center justify-between gap-3">
                                    <button type="button" @click="back()" class="{{ $navGhost }}">
                                        <x-travel-icon name="arrow_back" class="h-[18px] w-[18px]" />
                                        {{ __('tourism.request.wizard_back') }}
                                    </button>
                                    <button type="submit" :disabled="!consented" class="{{ $navPrimary }} disabled:cursor-not-allowed disabled:bg-travel-primary disabled:opacity-50 disabled:shadow-none disabled:hover:bg-travel-primary">
                                        {{ __('tourism.request.submit_offers') }}
                                        <x-travel-icon name="arrow_forward" class="h-[18px] w-[18px]" />
                                    </button>
                                </div>

                                <p class="mt-3 flex items-center justify-center gap-1.5 text-label-caps text-ink-muted">
                                    <x-travel-icon name="lock" class="h-3.5 w-3.5 shrink-0" />
                                    {{ __('tourism.request.safe_secure') }}
                                </p>
                            </section>
                        </div>
                    </div>

                    <div class="relative">
                        @include('tourism.request._summary')
                    </div>
                </div>

                @include('tourism.request._mobile-bar')
            </form>

            @error('destination_countries')
                @php $firstDestination = collect((array) old('destination_countries', []))->first(); @endphp
                @if ($firstDestination)
                    <form id="notify-me-form" method="POST" action="{{ route('tourism.destination-alerts.store') }}" class="hidden">
                        @csrf
                        <input type="hidden" name="destination_country" value="{{ $firstDestination }}">
                    </form>
                @endif
            @enderror
        </section>
    </div>
@endsection
