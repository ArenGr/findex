@extends('layouts.app')

@section('title', __('tourism.request.heading') . ' — Findex')

{{-- The flat site bar, not the floating capsule: the design opens on a
     gradient that runs from under the header, and a card floating over it cut
     the gradient in two. --}}

{{-- This page sets font-jakarta on its wrapper, so Plus Jakarta Sans is its
     body face at every weight. Preloaded here rather than in the layout: no
     other page uses it. Without it the whole page paints in the fallback and
     then re-renders once Jakarta lands. --}}
@push('head')
    @foreach (App\Support\FontPreloads::urls('plus-jakarta-sans', app()->getLocale()) as $href)
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ $href }}">
    @endforeach
@endpush

@php
    use App\Models\QuoteRequest;

    // The page's shared shapes, stated once. `$fieldIcon` is the same field
    // with room for a leading glyph.
    $field = 'w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors placeholder:text-gray-400 focus:border-travel-600 focus:ring-1 focus:ring-travel-600 focus:outline-none';
    $fieldIcon = $field.' pl-10';
    $label = 'block text-xs font-semibold text-gray-600';
    $card = 'rounded-2xl border border-gray-200 bg-white p-6 sm:p-7';
    $cardHeading = 'text-lg font-bold text-gray-900';
    $stepper = 'flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-600 transition-colors hover:border-travel-600 hover:text-travel-700 disabled:opacity-40 disabled:hover:border-gray-200 disabled:hover:text-gray-600';

    // The selection pill, in its two states. Every group on the page - flights,
    // hotel class, meals, priorities, budget bands, date flexibility - is the
    // same control, and each had grown its own copy of these classes.
    $pill = 'inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-travel-600/40 focus-visible:outline-none';
    $pillOff = 'border-gray-200 bg-white font-medium text-gray-700 hover:border-travel-200 hover:bg-gray-50/70';
    $pillOn = 'border-travel-600 bg-travel-50 font-semibold text-travel-800';

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

    $navPrimary = 'inline-flex items-center gap-2 rounded-lg bg-travel-600 px-7 py-3 text-sm font-bold text-white shadow-md transition-all hover:bg-travel-700 focus-visible:ring-2 focus-visible:ring-travel-600/40 focus-visible:outline-none';
    $navGhost = 'inline-flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-travel-600/40 focus-visible:outline-none';
@endphp

@section('content')
    <div
        class="bg-white font-jakarta text-slate-800 accent-travel-600"
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
        {{-- On every step, not just the first.

             The hero used to be x-show="step === 1", with a bare heading and
             a step count standing in for it on steps 2 and 3. Moving off step
             1 therefore took the whole top of the page away and left the
             wizard hanging under a single line of text, so the steps did not
             read as three screens of one page - they read as the first screen
             and then something else. The page keeps its head throughout; the
             stepper inside the card is what says where you are. --}}
        @include('tourism.request._hero')

        @include('tourism.request._presets')

        <main class="travel-container pb-20">
            <div id="travel-form-top" class="scroll-mt-24"></div>
            @if (session('status') === 'destination-alert-created')
                <div class="mb-6 rounded-lg border border-travel-200 bg-travel-50 px-4 py-3 text-sm text-travel-800">
                    {{ __('tourism.request.notify_me_confirmed') }}
                </div>
            @endif

            @if (session('status') === 'email-verification-required')
                <div class="mb-6 rounded-lg border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-gray-800">
                    {{ __('auth.verify_email.action_blocked') }}
                </div>
            @endif

            <form method="POST" action="{{ route('tourism.request.store') }}" novalidate>
                @csrf

                <div class="hidden" aria-hidden="true">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
                </div>

                {{-- The card shell. The stepper is a bar of its own across the
                     top, on its own tint, rather than a row sharing the body's
                     padding - so progress reads as a property of the card and
                     not as the first thing inside the step. --}}
                {{-- relative z-10 and a deeper shadow: page and card are both
                     white now, so nothing but elevation separates them, and
                     the card has to sit clearly above the hero's gradient
                     rather than blend into it. --}}
                <div class="relative z-10 overflow-hidden rounded-3xl bg-white shadow-[0_24px_60px_-18px_rgba(15,23,42,0.22)] ring-1 ring-gray-200/80">
                    {{-- White like the rest of the body, delimited by its rule
                         rather than by a tint. --}}
                    <div class="border-b border-gray-100 px-6 py-5 sm:px-12">
                        @include('tourism.request._stepper')
                    </div>

                    <div class="grid grid-cols-1 gap-8 p-6 sm:p-10 lg:grid-cols-12 lg:items-start lg:p-12">
                        {{-- The step viewport.

                             One step is on screen at a time and the other two
                             are `display: none` - not stacked on top of it at
                             opacity 0 inside a box whose height is animated
                             between them, which is what this was. That read as
                             the first step growing to take in the second one's
                             fields rather than as moving to a new screen, and
                             it crossfaded the two forms over each other on the
                             way.

                             Which step is shown is decided by a class, not by
                             x-show: the server prints it for the step this
                             request opens on, so the right one is on screen in
                             the first frame rather than after Alpine boots. --}}
                        <div class="relative lg:col-span-8">
                            {{-- STEP 1 --}}
                            <div
                                data-step="1"
                                x-ref="slide1"
                                {{-- Object form, so Alpine takes away whichever
                                     of the two the server printed once it no
                                     longer applies. --}}
                                :class="{ 'flex': step === 1, 'hidden': step !== 1 }"
                                @class([
                                    'travel-step w-full flex-col gap-4',
                                    // Server-rendered to match the step this request opens on,
                                    // so a validation error that reopens step 2 paints step 2.
                                    'flex' => $initialStep === 1,
                                    'hidden' => $initialStep !== 1,
                                ])
                            >
                                {{-- sr-only: the card below opens with its own
                                     "Trip details" heading, so painting this one too
                                     gave the step two titles and cost ~85px of the
                                     wizard's height. It stays in the accessibility tree
                                     and remains the slide's focus target. --}}
                                <h2 x-ref="heading1" tabindex="-1" class="sr-only">{{ __('tourism.request.step1_heading') }}</h2>

                                @include('tourism.request._voice-fill')
                                @include('tourism.request._trip-details')

                            </div>

                            {{-- STEP 2 --}}
                            <div
                                data-step="2"
                                x-ref="slide2"
                                {{-- Object form, so Alpine takes away whichever
                                     of the two the server printed once it no
                                     longer applies. --}}
                                :class="{ 'flex': step === 2, 'hidden': step !== 2 }"
                                @class([
                                    'travel-step w-full flex-col gap-4',
                                    // Server-rendered to match the step this request opens on,
                                    // so a validation error that reopens step 2 paints step 2.
                                    'flex' => $initialStep === 2,
                                    'hidden' => $initialStep !== 2,
                                ])
                            >
                                {{-- tabindex="-1" so goToStep() can move focus here; it is
                                     not a tab stop, only a focus target. --}}
                                <div class="space-y-1">
                                    <h2 x-ref="heading2" tabindex="-1" class="text-2xl font-bold text-travel-ink outline-none">{{ __('tourism.request.step2_heading') }}</h2>
                                    <p class="text-sm text-gray-500">{{ __('tourism.request.step2_sub') }}</p>
                                </div>

                                {{-- Two columns, not three stacked cards. Step 2
                                     carries the most content of the three and
                                     ran to ~1260px tall against step 1's ~600,
                                     which made the wizard feel like a different,
                                     much longer page rather than the next screen
                                     of the same one. Balanced by hand - the
                                     preferences card is roughly as tall as the
                                     other two together - because grid
                                     auto-placement would put them on two rows
                                     and save nothing. --}}
                                {{-- Stacked, each across the width of the form
                                     column. Set side by side these cards are
                                     about 280px wide, which wraps every pill
                                     group onto three and four rows - "Half
                                     board" and "Full board" end up on lines of
                                     their own - and the two columns never come
                                     out the same height anyway. --}}
                                @include('tourism.request._preferences')
                                @include('tourism.request._priorities')
                                @include('tourism.request._budget-notes')

                                <div class="flex items-center justify-between gap-3 pt-4">
                                    <button type="button" @click="back()" class="{{ $navGhost }}">
                                        <x-travel-icon name="arrow_back" class="h-4 w-4" />
                                        {{ __('tourism.request.wizard_back') }}
                                    </button>
                                    <button type="button" @click="next()" class="{{ $navPrimary }}">
                                        {{ __('tourism.request.wizard_continue') }}
                                        <x-travel-icon name="arrow_forward" class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            {{-- STEP 3 --}}
                            <div
                                data-step="3"
                                x-ref="slide3"
                                {{-- Object form, so Alpine takes away whichever
                                     of the two the server printed once it no
                                     longer applies. --}}
                                :class="{ 'flex': step === 3, 'hidden': step !== 3 }"
                                @class([
                                    'travel-step w-full flex-col gap-4',
                                    // Server-rendered to match the step this request opens on,
                                    // so a validation error that reopens step 2 paints step 2.
                                    'flex' => $initialStep === 3,
                                    'hidden' => $initialStep !== 3,
                                ])
                            >
                                {{-- tabindex="-1" so goToStep() can move focus here; it is
                                     not a tab stop, only a focus target. --}}
                                <div class="space-y-1">
                                    <h2 x-ref="heading3" tabindex="-1" class="text-2xl font-bold text-travel-ink outline-none">{{ __('tourism.request.step3_heading') }}</h2>
                                    <p class="text-sm text-gray-500">{{ __('tourism.request.step3_sub') }}</p>
                                </div>

                                {{-- Arriving here from a popular trip skips
                                     two screens, so it has to be said that the
                                     answers below were filled in rather than
                                     given. --}}
                                <p
                                    x-show="preset"
                                    x-cloak
                                    class="flex items-center gap-2 rounded-xl border border-travel-200 bg-travel-50 px-4 py-3 text-xs font-medium text-travel-800"
                                >
                                    <x-travel-icon name="check" class="h-4 w-4 shrink-0 text-travel-600" />
                                    {{ __('tourism.presets.applied') }}
                                </p>

                                {{-- md:hidden: from md up the summary panel is
                                     beside this column, sticky and already
                                     showing every one of these rows plus the
                                     three this card leaves out - so on a
                                     desktop the step opened with the same
                                     "Your trip" panel printed twice, side by
                                     side. Below md the panel is further down
                                     the page, and this is the only review
                                     within reach of the submit button. --}}
                                <section class="{{ $card }} lg:hidden">
                                    <div class="mb-4 flex items-center justify-between gap-3">
                                        <h3 class="text-base font-bold text-gray-900">{{ __('tourism.request.review_heading') }}</h3>
                                        <button type="button" @click="goToStep(1)" class="text-xs font-bold text-travel-600 hover:underline">{{ __('tourism.request.summary_edit') }}</button>
                                    </div>

                                    <div x-show="hasItinerary" x-cloak class="mb-4 border-b border-gray-100 pb-4">
                                        <p class="text-base font-bold text-gray-900" x-text="itineraryRoute"></p>
                                        <p class="mt-1 text-xs text-gray-500" x-text="itineraryMeta"></p>
                                    </div>

                                    <dl class="flex flex-col gap-2.5">
                                        @foreach ([
                                            ['label' => __('tourism.request.summary_flight'), 'value' => 'flightSummary'],
                                            ['label' => __('tourism.request.summary_hotel'), 'value' => 'hotelSummary'],
                                            ['label' => __('tourism.request.summary_meals'), 'value' => 'mealsSummary'],
                                            ['label' => __('tourism.request.summary_budget'), 'value' => 'budgetSummary'],
                                        ] as $row)
                                            <div class="flex justify-between gap-3 text-xs">
                                                <dt class="shrink-0 text-gray-500">{{ $row['label'] }}</dt>
                                                <dd class="text-right font-semibold text-gray-900" x-text="{{ $row['value'] }}"></dd>
                                            </div>
                                        @endforeach
                                        <div x-show="priorities.length" x-cloak class="flex justify-between gap-3 border-t border-gray-100 pt-2.5 text-xs">
                                            <dt class="shrink-0 text-gray-500">{{ __('tourism.request.priorities_label') }}</dt>
                                            <dd class="flex flex-wrap justify-end gap-1.5">
                                                <template x-for="value in priorities" :key="value">
                                                    <span class="rounded-full border border-travel-200 bg-travel-50 px-2.5 py-1 text-[11px] font-semibold text-travel-700" x-text="@js($priorityOptions)[value]"></span>
                                                </template>
                                            </dd>
                                        </div>
                                    </dl>
                                </section>

                                <section class="{{ $card }} space-y-5">
                                    <div class="flex items-center gap-3">
                                        <x-travel-section-icon name="mail" />
                                        <h3 class="{{ $cardHeading }}">{{ __('tourism.request.section_details') }}</h3>
                                    </div>

                                    @guest
                                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                            <div class="flex flex-col gap-1.5">
                                                <label for="guest_name" class="{{ $label }}">{{ __('tourism.request.your_name') }}</label>
                                                <input type="text" name="guest_name" id="guest_name" value="{{ old('guest_name') }}" required class="{{ $field }} @error('guest_name') border-error @enderror">
                                                @error('guest_name')
                                                    <p class="text-xs text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="flex flex-col gap-1.5">
                                                <label for="guest_email" class="{{ $label }}">{{ __('tourism.request.your_email') }}</label>
                                                <input type="email" name="guest_email" id="guest_email" value="{{ old('guest_email') }}" required class="{{ $field }} @error('guest_email') border-error @enderror">
                                                @error('guest_email')
                                                    <p class="text-xs text-error">{{ $message }}</p>
                                                @enderror
                                                <p class="text-xs text-gray-500">{{ __('tourism.request.your_email_hint') }}</p>
                                            </div>
                                        </div>
                                    @endguest

                                    @auth
                                        <p class="text-xs text-gray-500">
                                            {{ __('tourism.request.your_email_hint') }}
                                            <span class="font-semibold text-gray-900">{{ auth()->user()->email }}</span>
                                        </p>
                                    @endauth
                                </section>

                                <section class="{{ $card }}">
                                    <label class="flex cursor-pointer items-start gap-2.5 text-sm text-gray-700">
                                        <input type="checkbox" name="consent" value="1" x-model="consented" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-travel-600 focus:ring-travel-600">
                                        <span>{{ __('tourism.request.consent') }}</span>
                                    </label>
                                    @error('consent')
                                        <p class="mt-2 text-xs text-error">{{ $message }}</p>
                                    @enderror

                                    <div class="mt-6 flex items-center justify-between gap-3">
                                        <button type="button" @click="back()" class="{{ $navGhost }}">
                                            <x-travel-icon name="arrow_back" class="h-4 w-4" />
                                            {{ __('tourism.request.wizard_back') }}
                                        </button>
                                        <button type="submit" :disabled="!consented" class="{{ $navPrimary }} disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:hover:bg-travel-600">
                                            {{ __('tourism.request.submit_offers') }}
                                            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <p class="mt-4 flex items-center justify-center gap-1.5 text-xs font-medium text-gray-500">
                                        <x-travel-icon name="lock" class="h-3.5 w-3.5 shrink-0" />
                                        {{ __('tourism.request.safe_secure') }}
                                    </p>
                                </section>
                            </div>
                        </div>

                        <aside class="lg:col-span-4">
                            @include('tourism.request._summary')
                        </aside>
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
        </main>

        {{-- All three shown on every step: someone who reached step 3 is
             exactly the person deciding whether to trust what happens next. --}}
        @include('tourism.request._how-it-works')
        <x-travel.partners :partners="$partners" />
        @include('tourism.request._closing-band')
    </div>
@endsection
