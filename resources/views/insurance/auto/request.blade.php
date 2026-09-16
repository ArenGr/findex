@extends('layouts.app')

@section('title', __('auto_insurance.request.heading') . ' — Findex')

@php
    // The rates page's panel, label, field and chip - see rates/index.
    $panel = 'rounded-3xl border border-border bg-surface p-5 sm:p-6';
    $labelClass = 'block text-xs font-semibold tracking-wider text-muted uppercase';
    $error = 'mt-1.5 text-xs text-accent-red';

    $vehicleFields = [
        ['name' => 'vehicle_plate', 'type' => 'text', 'icon' => 'car', 'label' => __('auto_insurance.request.vehicle_plate'), 'placeholder' => __('auto_insurance.request.vehicle_plate_placeholder'), 'class' => 'uppercase placeholder:normal-case'],
        ['name' => 'owner_id_number', 'type' => 'text', 'icon' => 'id-card', 'label' => __('auto_insurance.request.owner_id_number')],
    ];

    $contactFields = [
        ...(auth()->guest() ? [
            ['name' => 'guest_name', 'type' => 'text', 'icon' => 'user', 'label' => __('tourism.request.your_name')],
            ['name' => 'guest_email', 'type' => 'email', 'icon' => 'mail', 'label' => __('tourism.request.your_email')],
        ] : []),
        ['name' => 'market_phone', 'type' => 'tel', 'icon' => 'phone', 'label' => __('auto_insurance.request.market_phone')],
        ['name' => 'market_email', 'type' => 'email', 'icon' => 'mail', 'label' => __('auto_insurance.request.market_email')],
        ['name' => 'market_bank_account', 'type' => 'text', 'icon' => 'landmark', 'label' => __('auto_insurance.request.market_bank_account'), 'hint' => __('auto_insurance.request.market_bank_account_hint')],
    ];
@endphp

@section('content')
    <x-vertical-hero
        icon="shield-check"
        :eyebrow="__('nav.insurance.label')"
        :title="__('auto_insurance.request.heading')"
        :subtitle="__('auto_insurance.request.subheading')"
        :signals="[
            ['icon' => 'building-2', 'title' => trans_choice('compare_ui.insurers', $insurerCount, ['count' => $insurerCount]), 'sub' => __('auto_insurance.request.signal_insurers_sub')],
            ['icon' => 'hand-coins', 'title' => __('auto_insurance.request.signal_free'), 'sub' => __('auto_insurance.request.signal_free_sub')],
            ['icon' => 'clock', 'title' => __('auto_insurance.request.signal_fast'), 'sub' => __('auto_insurance.request.signal_fast_sub')],
        ]"
    />

    <section class="site-container pt-9 pb-12">
        <form
            method="POST"
            action="{{ route('insurance.auto.request.store') }}"
            class="{{ $panel }}"
            novalidate
            x-data="{ loading: false }"
            @submit="loading = true"
        >
            <x-findex-loader
                :title="__('auto_insurance.loading.title')"
                :subtitle="__('auto_insurance.loading.subtitle')"
                :count="$insurerCount"
            />
            @csrf

            {{-- Honeypot: a bot filling every field trips it (see AutoInsuranceController::store). --}}
            <div class="hidden" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>

            @error('insurance_quote')
                <div class="mb-5 rounded-xl border border-accent-red/30 bg-accent-red/5 px-4 py-3 text-sm text-accent-red">{{ $message }}</div>
            @enderror

            {{-- Vehicle: the fields left, the term chips right - the rates panel's first row. --}}
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-start">
                @foreach ($vehicleFields as $field)
                    @include('insurance.auto._field', ['field' => $field])
                @endforeach

                <fieldset>
                    <legend class="block text-[13px] font-semibold text-ink">{{ __('auto_insurance.request.contract_term') }}</legend>
                    <div class="mt-2 grid grid-cols-3 gap-2 lg:flex">
                        @foreach ($contractTerms as $term)
                            <label class="cursor-pointer">
                                <input type="radio" name="contract_term_months" value="{{ $term }}" class="peer sr-only" @checked((int) old('contract_term_months', 12) === $term) required>
                                <span class="flex min-h-[3.25rem] items-center justify-center gap-1.5 rounded-lg border border-placeholder bg-white px-4 text-sm font-medium whitespace-nowrap text-muted transition hover:border-primary hover:bg-primary/10 hover:text-primary peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-checked:hover:bg-primary-dark peer-focus-visible:ring-2 peer-focus-visible:ring-primary/30">
                                    {{ __('auto_insurance.request.contract_terms.' . $term) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('contract_term_months')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </fieldset>
            </div>

            {{-- Contact and payout, under a rule like the rates filters. --}}
            <div class="mt-5 border-t border-border pt-5">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                    <h2 class="{{ $labelClass }}">{{ __('auto_insurance.request.market_heading') }}</h2>
                    <p class="inline-flex items-center gap-1.5 text-xs text-muted">
                        <x-lucide name="lock" :size="14" class="text-primary" />
                        {{ __('auto_insurance.request.aside_secure_title') }}
                    </p>
                </div>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-muted">{{ __('auto_insurance.request.market_explainer') }}</p>

                <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($contactFields as $field)
                        @include('insurance.auto._field', ['field' => $field])
                    @endforeach
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-5 border-t border-border pt-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="consent" value="1" required class="mt-0.5 h-[18px] w-[18px] shrink-0 rounded border-placeholder text-primary focus:ring-primary">
                        <span class="max-w-2xl text-sm leading-relaxed text-muted">{{ __('auto_insurance.request.consent') }}</span>
                    </label>
                    @error('consent')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                <button type="submit" :disabled="loading" class="btn btn-primary shrink-0 lg:min-w-[12rem]">
                    <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                    </svg>
                    <span x-text="loading ? @js(__('auto_insurance.loading.title')) : @js(__('auto_insurance.request.submit'))">{{ __('auto_insurance.request.submit') }}</span>
                    <x-lucide x-show="!loading" name="arrow-right" :size="18" />
                </button>
            </div>
        </form>

        <p class="mt-8 border-t border-placeholder pt-5 text-xs leading-relaxed text-muted">
            {{ __('auto_insurance.request.aside_secure_body') }}
        </p>
    </section>

    <x-how-it-works
        :heading="__('auto_insurance.request.works_heading')"
        :sub="__('auto_insurance.request.works_sub')"
        :steps="[
            ['icon' => 'car', 'title' => __('auto_insurance.request.works_1_title'), 'body' => __('auto_insurance.request.works_1_body')],
            ['icon' => 'building-2', 'title' => __('auto_insurance.request.works_2_title'), 'body' => __('auto_insurance.request.works_2_body')],
            ['icon' => 'scale', 'title' => __('auto_insurance.request.works_3_title'), 'body' => __('auto_insurance.request.works_3_body')],
            ['icon' => 'circle-check', 'title' => __('auto_insurance.request.works_4_title'), 'body' => __('auto_insurance.request.works_4_body')],
        ]"
    />

    <x-partners-strip :partners="$partners" :heading="__('auto_insurance.request.partners_heading')" marquee />
@endsection
