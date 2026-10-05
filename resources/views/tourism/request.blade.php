@extends('layouts.app')

@section('title', __('tourism.request.heading') . ' — Findex')

@php
    use App\Models\QuoteRequest;

    // The page's shared shapes, stated once.
    $field = 'field';
    $fieldIcon = 'field field-icon';
    $label = 'block text-[13px] font-semibold text-ink';
    // Sections inside the one panel are split by a rule, not boxed again.
    $card = 'border-t border-border pt-6';
    $cardHeading = 'font-heading text-base font-bold text-ink';
    $stepper = 'flex h-8 w-8 items-center justify-center rounded-full border border-border text-muted transition-colors hover:border-primary hover:text-primary-dark disabled:opacity-40 disabled:hover:border-border disabled:hover:text-muted';

    // The selection pill, in its two states.
    $pill = 'inline-flex items-center gap-1.5 min-h-11 rounded-lg border px-4 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none';
    $pillOff = 'border-placeholder bg-white text-muted hover:border-primary hover:bg-primary/10 hover:text-primary';
    $pillOn = 'border-primary bg-primary text-white hover:bg-primary-dark';

    // Which wizard step a failed submission should reopen: the earliest step holding a rejected field.
    $stepFields = [
        1 => ['departure_location', 'destination_countries', 'check_in', 'check_out', 'date_flexibility', 'adults', 'children', 'child_ages'],
        2 => ['flight_preference', 'hotel_preference', 'meal_preference', 'hotel_name', 'priorities', 'budget_band', 'budget_min_amd', 'budget_max_amd', 'notes', 'insurance'],
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

    $navPrimary = 'btn btn-primary';
    $navGhost = 'btn btn-secondary';
@endphp

@section('content')
    <div
        class="bg-white text-ink accent-primary"
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
        {{-- On every step, not just the first. --}}
        @include('tourism.request._hero')


        <main class="site-container pt-9 pb-12">
            <div id="travel-form-top" class="scroll-mt-24"></div>
            @if (session('status') === 'destination-alert-created')
                <div class="mb-6 rounded-lg border border-border bg-surface-alt px-4 py-3 text-sm text-primary-dark">
                    {{ __('tourism.request.notify_me_confirmed') }}
                </div>
            @endif

            @if (session('status') === 'email-verification-required')
                <div class="mb-6 rounded-lg border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                    {{ __('auth.verify_email.action_blocked') }}
                </div>
            @endif

            <form method="POST" action="{{ route('tourism.request.store') }}" novalidate>
                @csrf

                <div class="hidden" aria-hidden="true">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
                </div>

                {{-- White on white: a heavier border and a soft shadow lift the form off the page. --}}
                <div class="form-lg relative z-10 w-full overflow-hidden rounded-3xl border-2 border-border bg-surface shadow-[0_18px_44px_-28px_rgb(24_29_18/0.45)]">
                    {{-- The form keeps a readable measure; the rail beside it holds the trip so far. --}}
                    <div class="grid lg:grid-cols-[minmax(0,1fr)_21rem]">
                        <div class="p-5 sm:p-8 lg:p-10">
                            @include('tourism.request._stepper')

                        {{-- The step viewport. --}}
                        <div class="relative pt-8">
                            {{-- STEP 1 --}}
                            <div
                                data-step="1"
                                x-ref="slide1"
                                :class="{ 'flex': step === 1, 'hidden': step !== 1 }"
                                @class([
                                    'travel-step w-full flex-col gap-4',
                                    'flex' => $initialStep === 1,
                                    'hidden' => $initialStep !== 1,
                                ])
                            >
                                <h2 x-ref="heading1" tabindex="-1" class="sr-only">{{ __('tourism.request.step1_heading') }}</h2>

                                @include('tourism.request._voice-fill')
                                @include('tourism.request._trip-details')

                            </div>

                            {{-- STEP 2 --}}
                            <div
                                data-step="2"
                                x-ref="slide2"
                                :class="{ 'flex': step === 2, 'hidden': step !== 2 }"
                                @class([
                                    'travel-step w-full flex-col gap-4',
                                    'flex' => $initialStep === 2,
                                    'hidden' => $initialStep !== 2,
                                ])
                            >
                                {{-- tabindex="-1" so goToStep() can move focus here; it is not a tab stop, only a focus target. --}}
                                <div class="space-y-1">
                                    <h2 x-ref="heading2" tabindex="-1" class="font-heading text-2xl leading-tight font-bold text-ink outline-none sm:text-3xl">{{ __('tourism.request.step2_heading') }}</h2>
                                    <p class="text-sm text-muted">{{ __('tourism.request.step2_sub') }}</p>
                                </div>

                                {{-- Two columns, not three stacked cards. --}}
                                {{-- Stacked, each across the width of the form column. --}}
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
                                :class="{ 'flex': step === 3, 'hidden': step !== 3 }"
                                @class([
                                    'travel-step w-full flex-col gap-4',
                                    'flex' => $initialStep === 3,
                                    'hidden' => $initialStep !== 3,
                                ])
                            >
                                {{-- tabindex="-1" so goToStep() can move focus here; it is not a tab stop, only a focus target. --}}
                                <div class="space-y-1">
                                    <h2 x-ref="heading3" tabindex="-1" class="font-heading text-2xl leading-tight font-bold text-ink outline-none sm:text-3xl">{{ __('tourism.request.step3_heading') }}</h2>
                                    <p class="text-sm text-muted">{{ __('tourism.request.step3_sub') }}</p>
                                </div>

                                <p
                                    x-show="preset"
                                    x-cloak
                                    class="flex items-center gap-2 rounded-xl border border-border bg-surface-alt px-4 py-3 text-xs font-medium text-primary-dark"
                                >
                                    <x-travel-icon name="check" class="h-4 w-4 shrink-0 text-primary" />
                                    {{ __('tourism.presets.applied') }}
                                </p>

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
                                                <p class="text-xs text-muted">{{ __('tourism.request.your_email_hint') }}</p>
                                            </div>
                                        </div>
                                    @endguest

                                    @auth
                                        <p class="text-xs text-muted">
                                            {{ __('tourism.request.your_email_hint') }}
                                            <span class="font-semibold text-ink">{{ auth()->user()->email }}</span>
                                        </p>
                                    @endauth
                                </section>

                                <section class="{{ $card }}">
                                    <label class="flex cursor-pointer items-start gap-2.5 text-sm text-muted">
                                        <input type="checkbox" name="consent" value="1" x-model="consented" class="mt-0.5 h-4 w-4 shrink-0 rounded border-border-muted text-primary focus:ring-primary">
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
                                        <button type="submit" :disabled="!consented" class="{{ $navPrimary }} disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:hover:bg-primary">
                                            {{ __('tourism.request.submit_offers') }}
                                            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </section>
                            </div>
                        </div>

                        </div>

                        <aside class="border-t border-border bg-surface-alt p-5 sm:p-8 lg:border-t-0 lg:border-l lg:p-8">
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

        {{-- After the form, not before it: the page asks first and
             suggests second, like rates and insurance. --}}
        @include('tourism.request._presets')

        <x-how-it-works
            tone="travel"
            :heading="__('tourism.request.works_heading')"
            :sub="__('tourism.request.works_sub')"
            :steps="[
                ['icon' => 'map', 'title' => __('tourism.request.step_1_title'), 'body' => __('tourism.request.step_1_body')],
                ['icon' => 'mail', 'title' => __('tourism.request.step_2_title'), 'body' => __('tourism.request.step_2_body')],
                ['icon' => 'scale', 'title' => __('tourism.request.step_3_title'), 'body' => __('tourism.request.step_3_body')],
                ['icon' => 'plane', 'title' => __('tourism.request.step_4_title'), 'body' => __('tourism.request.step_4_body')],
            ]"
        />

        <x-partners-strip :partners="$partners" :heading="__('tourism.request.partners_heading')" marquee />
    </div>
@endsection
