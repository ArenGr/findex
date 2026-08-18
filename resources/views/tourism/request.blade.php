@extends('layouts.app')

@section('title', __('tourism.request.heading') . ' — Findex')

@php
    use App\Models\QuoteRequest;

    // Field and card styling from the approved travel designs, stated once
    // rather than re-typed across the dozen-odd controls below. Shared with
    // the section partials via @include, which inherits this scope.
    $field = 'w-full rounded-lg border border-border-subtle bg-white px-4 py-3 text-body-sm text-on-surface transition-colors focus:border-travel-primary focus:ring-1 focus:ring-travel-primary focus:outline-none';
    $label = 'block text-body-sm text-ink-muted';
    $card = 'rounded-xl border border-border-subtle bg-white p-5 sm:p-8';
    $stepper = 'flex h-8 w-8 items-center justify-center rounded-full border border-border-subtle text-on-surface transition-colors hover:border-travel-primary disabled:opacity-40 disabled:hover:border-border-subtle';
@endphp

@section('content')
    {{-- The travel flow carries its own palette and type scale (see the
         travel block in app.css); font-manrope on the wrapper hands it to
         everything inside without touching the rest of the site. --}}
    <div class="bg-surface-alt font-manrope text-on-surface">
        <section class="mx-auto max-w-[1280px] px-4 py-10 md:px-10 md:py-12">
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

            <header class="mb-8">
                <p class="mb-2 text-label-caps text-travel-primary">{{ __('tourism.request.eyebrow') }}</p>
                <h1 class="text-headline-lg-mobile text-on-surface md:text-headline-lg">{{ __('tourism.request.heading') }}</h1>
                <p class="mt-1 max-w-3xl text-body-lg text-ink-muted">{{ __('tourism.request.subheading') }}</p>
            </header>

            {{-- One shared x-data for the whole form, so the summary panel and
                 the mobile action bar derive from the same state the form
                 submits rather than a second copy kept in step by hand. The
                 component itself lives in resources/js - an object this size
                 inside an HTML attribute is unreadable and, worse, breaks
                 outright on a single double quote. --}}
            <form
                method="POST"
                action="{{ route('tourism.request.store') }}"
                novalidate
                x-data="travelRequestForm(@js([
                    'countries' => $countries,
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
                @csrf

                {{-- Honeypot: hidden from real visitors, a bot filling every
                     field trips it (see QuoteRequestController::store). --}}
                <div class="hidden" aria-hidden="true">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-12">
                    {{-- Left column: the form --}}
                    <div class="flex flex-col gap-6 md:col-span-9">
                        @include('tourism.request._voice-fill')
                        @include('tourism.request._trip-details')
                        @include('tourism.request._preferences')
                        @include('tourism.request._priorities')
                        @include('tourism.request._budget-notes')

                        @guest
                            {{-- Not in the designs, which assume a signed-in
                                 traveller - but a guest has no account to send
                                 the results link to, so this still has to be
                                 asked. --}}
                            <section class="{{ $card }}">
                                <div class="mb-6 flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-travel-primary/10">
                                        <x-travel-icon name="group" class="h-5 w-5 text-travel-primary" />
                                    </span>
                                    <h2 class="text-headline-md">{{ __('tourism.request.section_details') }}</h2>
                                </div>

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
                                        <p class="text-body-sm text-ink-muted">
                                            {{ __('tourism.request.your_email_hint') }}
                                            <a href="{{ route('tourism.resend') }}" class="text-travel-primary hover:underline">{{ __('tourism.resend.heading') }}</a>
                                        </p>
                                    </div>
                                </div>
                            </section>
                        @endguest
                    </div>

                    {{-- Right column: the live summary, sticky on desktop. On
                         mobile it becomes an ordinary card at the end of the
                         form and the compact bar takes over keeping the action
                         reachable. --}}
                    <div class="relative md:col-span-3">
                        @include('tourism.request._summary')
                    </div>
                </div>

                @include('tourism.request._mobile-bar')
            </form>

            {{-- Standalone, outside the main form - a nested <form> is invalid
                 HTML and gets silently dropped on parse. The visible notify-me
                 control targets this one via form="" instead. --}}
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
