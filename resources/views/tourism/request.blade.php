@extends('layouts.app')

@section('title', __('tourism.request.heading') . ' — Findex')

@php
    use App\Models\QuoteRequest;

    $field = 'w-full rounded-lg border border-border-subtle bg-white px-4 py-3 text-body-sm text-on-surface transition-colors focus:border-travel-primary focus:ring-1 focus:ring-travel-primary focus:outline-none';
    $label = 'block text-body-sm text-ink-muted';
    $card = 'rounded-[15px] border border-border-subtle bg-white p-5 shadow-[0_3px_14px_rgba(24,29,18,0.035)]';
    $stepper = 'flex h-9 w-9 items-center justify-center rounded-full border border-border-subtle text-on-surface transition-colors hover:border-travel-primary disabled:opacity-40 disabled:hover:border-border-subtle';

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

    $navPrimary = 'flex h-12 items-center justify-center gap-2 rounded-lg bg-travel-primary px-6 text-body-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#546d2d] focus-visible:ring-2 focus-visible:ring-travel-primary/40 focus-visible:outline-none';
    $navGhost = 'flex h-12 items-center justify-center gap-2 rounded-lg border border-border-subtle px-5 text-body-sm font-medium text-on-surface transition-colors hover:border-outline focus-visible:ring-2 focus-visible:ring-travel-primary/40 focus-visible:outline-none';
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
        <section x-show="step === 1" class="border-b border-placeholder bg-[radial-gradient(circle_at_78%_40%,rgba(96,126,52,0.10),transparent_30%)]">
            <div class="mx-auto grid max-w-[1220px] items-center gap-6 px-4 py-12 md:px-6 lg:grid-cols-[1fr_480px]">
                <div class="min-w-0">
                    <span class="mb-3 inline-flex items-center rounded-full bg-travel-primary/10 px-3 py-1 text-label-caps text-travel-primary">
                        {{ __('tourism.request.eyebrow') }}
                    </span>
                    <h1 class="text-headline-lg-mobile text-on-surface md:text-headline-lg">{{ __('tourism.request.heading') }}</h1>
                    <p class="mt-2 max-w-[570px] text-body-lg text-ink-muted">{{ __('tourism.request.subheading') }}</p>

                    <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2">
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
                </div>

                <div class="hidden items-center justify-end lg:flex">
                    <img src="{{ asset('images/travel/hero-travel.png') }}" alt="" class="h-[210px] w-full max-w-[480px] object-contain object-right">
                </div>
            </div>
        </section>

        <section x-show="step > 1" x-cloak class="border-b border-placeholder">
            <div class="mx-auto flex max-w-[1220px] items-center justify-between gap-4 px-4 py-4 md:px-6">
                <h1 class="text-headline-md text-on-surface">{{ __('tourism.request.heading') }}</h1>
                <span class="shrink-0 text-body-sm text-ink-muted" x-text="@js(__('tourism.request.wizard_step_of', ['current' => ':c', 'total' => ':t'])).replace(':c', step).replace(':t', totalSteps)"></span>
            </div>
        </section>

        <section class="mx-auto max-w-[1220px] px-4 pt-8 pb-16 md:px-6 md:pb-20">
            <div id="travel-form-top" class="scroll-mt-4"></div>

            <nav class="mb-8" aria-label="{{ __('tourism.request.heading') }}">
                <ol class="flex items-center">
                    @foreach ([1, 2, 3] as $n)
                        @php $done = "(step > {$n} || stepDone({$n}))"; @endphp
                        <li class="flex items-center gap-3 {{ $n < 3 ? 'flex-1' : '' }}">
                            <button
                                type="button"
                                @click="({{ $done }}) && goToStep({{ $n }})"
                                :class="{{ $done }} ? 'cursor-pointer' : (step === {{ $n }} ? '' : 'cursor-default')"
                                class="flex items-center gap-3 text-left focus-visible:outline-none"
                            >
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border text-[13px] font-semibold transition-colors"
                                    :class="{{ $done }}
                                        ? 'border-travel-primary bg-travel-primary text-white'
                                        : (step === {{ $n }} ? 'border-travel-primary text-travel-primary' : 'border-border-subtle text-ink-muted')"
                                >
                                    <template x-if="{{ $done }}"><x-travel-icon name="check" class="h-[18px] w-[18px]" /></template>
                                    <template x-if="!({{ $done }})"><span>{{ $n }}</span></template>
                                </span>
                                <span class="hidden min-w-0 sm:block">
                                    <span class="block text-[13px] font-semibold leading-4" :class="step === {{ $n }} ? 'text-on-surface' : 'text-ink-muted'">{{ __('tourism.request.fstep_' . $n . '_title') }}</span>
                                    <span class="block text-[11px] leading-4 text-ink-muted">{{ __('tourism.request.fstep_' . $n . '_body') }}</span>
                                </span>
                            </button>
                            @if ($n < 3)
                                <span class="mx-2 h-px flex-1 transition-colors" :class="step > {{ $n }} ? 'bg-travel-primary' : 'bg-border-subtle'"></span>
                            @endif
                        </li>
                    @endforeach
                </ol>

                <p class="mt-4 text-[13px] font-medium text-ink-muted sm:hidden" x-text="@js(__('tourism.request.wizard_step_of', ['current' => ':c', 'total' => ':t'])).replace(':c', step).replace(':t', totalSteps)"></p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-border-subtle sm:hidden">
                    <div class="h-full rounded-full bg-travel-primary transition-all" :style="`width: ${(step / totalSteps) * 100}%`"></div>
                </div>
            </nav>

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
                        <div data-step="1" x-show="step === 1" x-cloak class="flex flex-col gap-6">
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
                        <div data-step="2" x-show="step === 2" x-cloak class="flex flex-col gap-6">
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
                        <div data-step="3" x-show="step === 3" x-cloak class="flex flex-col gap-6">
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
