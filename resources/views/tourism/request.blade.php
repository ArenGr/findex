@extends('layouts.app')

@section('title', __('tourism.request.heading') . ' — Findex')

{{-- The travel page uses the floating capsule header from the design. --}}
@section('header-style', 'floating')

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
    $field = 'w-full rounded-lg border border-border-subtle bg-white px-3.5 py-3 text-body-sm text-on-surface transition-colors focus:border-travel-primary focus:ring-1 focus:ring-travel-primary focus:outline-none';
    $fieldIcon = $field.' pl-10';
    $label = 'block text-body-sm text-ink-muted';
    $card = 'rounded-[14px] border border-border-subtle bg-white p-5 shadow-[0_3px_14px_rgba(24,29,18,0.035)]';
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
        class="bg-white font-manrope text-on-surface"
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
        <div x-show="step === 1" @if ($initialStep !== 1) x-cloak @endif>
            @include('tourism.request._hero')
        </div>


        <section x-show="step > 1" @if ($initialStep <= 1) x-cloak @endif>
            <div class="site-container flex items-center justify-between gap-4 pt-8 lg:pt-10">
                <h1 class="font-heading text-[1.6rem] font-bold text-travel-ink">{{ __('tourism.request.heading') }}</h1>
                <span class="shrink-0 text-[13px] text-travel-muted" x-text="@js(__('tourism.request.wizard_step_of', ['current' => ':c', 'total' => ':t'])).replace(':c', step).replace(':t', totalSteps)"></span>
            </div>
        </section>

        <section class="pt-0 pb-12 lg:pb-14">
            <div id="travel-form-top" class="travel-wizard-container scroll-mt-4"></div>
            @if (session('status') === 'destination-alert-created')
                <div class="travel-wizard-container mb-6 rounded-lg border border-travel-primary/30 bg-travel-primary/5 px-4 py-3 text-body-sm text-travel-primary">
                    {{ __('tourism.request.notify_me_confirmed') }}
                </div>
            @endif

            @if (session('status') === 'email-verification-required')
                <div class="travel-wizard-container mb-6 rounded-lg border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-body-sm text-on-surface">
                    {{ __('auth.verify_email.action_blocked') }}
                </div>
            @endif

            <form method="POST" action="{{ route('tourism.request.store') }}" novalidate>
                @csrf

                <div class="hidden" aria-hidden="true">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
                </div>

                {{-- The overlap is bound as well as rendered: it belongs to
                     step 1, where there is a hero to overlap, and Alpine has to
                     be able to take it away again on steps 2 and 3. Object form,
                     so it removes the class the server printed. --}}
                <div
                    {{-- relative z-20: the hero is positioned, so a static card that
                         overlaps it would be painted underneath it. --}}
                    class="travel-wizard-container relative z-20 rounded-[24px] border border-black/5 bg-white p-5 shadow-[0_24px_70px_-12px_rgba(15,23,42,0.16)] sm:p-6 lg:p-7 {{ $initialStep === 1 ? 'lg:-mt-[78px]' : '' }}"
                    :class="{ 'lg:-mt-[78px]': step === 1 }"
                >
                    <div class="mb-6 border-b border-travel-border pb-6 lg:mb-8 lg:pb-7">
                        @include('tourism.request._stepper')
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-[minmax(0,1fr)_300px] lg:gap-7 xl:gap-8 xl:grid-cols-[minmax(0,1fr)_23%]">
                        {{-- The slide viewport.

                             One screen is on show at a time and the others are
                             taken out of flow, so the card's height is the
                             active screen's height - the steps are a carousel
                             inside one card, not three panels stacked down the
                             page. The height is bound rather than left to
                             `auto` so it can be transitioned between screens of
                             different heights instead of snapping. --}}
                        <div
                            class="relative overflow-hidden"
                            :class="sliderReady && 'transition-[height] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] motion-reduce:transition-none'"
                            :style="sliderReady ? `height: ${slideHeight}px` : ''"
                        >
                            {{-- STEP 1 --}}
                            {{-- x-cloak only on the steps this render is not
                                 starting on. Cloaking all three left the form
                                 blank until Alpine booted, so the page painted
                                 empty and then filled in. --}}
                            <div
                                data-step="1"
                                x-ref="slide1"
                                :class="slideClass(1)"
                                :inert="step !== 1"
                                :aria-hidden="step !== 1 ? 'true' : 'false'"
                                @class([
                                    'flex w-full flex-col gap-4 transition-[opacity,translate] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] motion-reduce:transition-none',
                                    // Server-rendered to match the step this request opens on,
                                    // so a validation error that reopens step 2 paints step 2 -
                                    // Alpine then keeps it in step via slideClass().
                                    'absolute inset-x-0 top-0 opacity-0 pointer-events-none' => $initialStep !== 1,
                                    '-translate-x-10' => 1 < $initialStep,
                                    'translate-x-10' => 1 > $initialStep,
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
                                :class="slideClass(2)"
                                :inert="step !== 2"
                                :aria-hidden="step !== 2 ? 'true' : 'false'"
                                @class([
                                    'flex w-full flex-col gap-4 transition-[opacity,translate] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] motion-reduce:transition-none',
                                    // Server-rendered to match the step this request opens on,
                                    // so a validation error that reopens step 2 paints step 2 -
                                    // Alpine then keeps it in step via slideClass().
                                    'absolute inset-x-0 top-0 opacity-0 pointer-events-none' => $initialStep !== 2,
                                    '-translate-x-10' => 2 < $initialStep,
                                    'translate-x-10' => 2 > $initialStep,
                                ])
                            >
                                <div>
                                    {{-- tabindex="-1" so goToStep() can move focus here; it is not a
                                             tab stop, only a focus target. --}}
                                        <h2 x-ref="heading2" tabindex="-1" class="text-headline-md text-travel-ink outline-none">{{ __('tourism.request.step2_heading') }}</h2>
                                    <p class="mt-1 text-body-md text-ink-muted">{{ __('tourism.request.step2_sub') }}</p>
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
                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
                                    @include('tourism.request._preferences')

                                    <div class="flex flex-col gap-4">
                                        @include('tourism.request._priorities')
                                        @include('tourism.request._budget-notes')
                                    </div>
                                </div>

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
                            <div
                                data-step="3"
                                x-ref="slide3"
                                :class="slideClass(3)"
                                :inert="step !== 3"
                                :aria-hidden="step !== 3 ? 'true' : 'false'"
                                @class([
                                    'flex w-full flex-col gap-4 transition-[opacity,translate] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] motion-reduce:transition-none',
                                    // Server-rendered to match the step this request opens on,
                                    // so a validation error that reopens step 2 paints step 2 -
                                    // Alpine then keeps it in step via slideClass().
                                    'absolute inset-x-0 top-0 opacity-0 pointer-events-none' => $initialStep !== 3,
                                    '-translate-x-10' => 3 < $initialStep,
                                    'translate-x-10' => 3 > $initialStep,
                                ])
                            >
                                <div>
                                    {{-- tabindex="-1" so goToStep() can move focus here; it is not a
                                             tab stop, only a focus target. --}}
                                        <h2 x-ref="heading3" tabindex="-1" class="text-headline-md text-travel-ink outline-none">{{ __('tourism.request.step3_heading') }}</h2>
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

        {{-- All three shown on every step: someone who reached step 3 is
             exactly the person deciding whether to trust what happens next. --}}
        @include('tourism.request._how-it-works')
        <x-travel.partners :partners="$partners" />
        @include('tourism.request._closing-band')
    </div>
@endsection
