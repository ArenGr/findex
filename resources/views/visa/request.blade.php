@extends('layouts.app')

@section('title', __('visa.request.heading') . ' — Findex')

@php
    // The rates panel, label and field - one design across every vertical.
    $panel = 'rounded-3xl border border-border bg-surface p-5 sm:p-6';
    $labelClass = 'block text-xs font-semibold tracking-wider text-muted uppercase';
    $fieldLabel = 'block text-[13px] font-semibold text-ink';
    $error = 'mt-1.5 text-xs text-accent-red';
@endphp

@section('content')
    <x-vertical-hero
        icon="ticket"
        :eyebrow="__('visa.request.eyebrow')"
        :title="__('visa.request.heading')"
        :subtitle="__('visa.request.subheading')"
        :signals="[
            ['icon' => 'badge-check', 'title' => __('visa.request.signal_agencies'), 'sub' => __('visa.request.signal_agencies_sub')],
            ['icon' => 'hand-coins', 'title' => __('visa.request.signal_free'), 'sub' => __('visa.request.signal_free_sub')],
            ['icon' => 'clock', 'title' => __('visa.request.signal_fast'), 'sub' => __('visa.request.signal_fast_sub')],
        ]"
    />

    <section class="site-container pt-9 pb-12">
        @if (session('status') === 'email-verification-required')
            <div class="mb-6 rounded-xl border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                {{ __('auth.verify_email.action_blocked') }}
            </div>
        @endif

        <form method="POST" action="{{ route('visa.request.store') }}" class="{{ $panel }}" novalidate>
            @csrf

            {{-- Honeypot: hidden from real visitors, so anything here is a bot. --}}
            <div class="hidden" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>

            <h2 class="{{ $labelClass }}">{{ __('visa.request.section_trip') }}</h2>

            <div class="mt-3 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0 lg:col-span-2">
                    <label for="destination_country" class="{{ $fieldLabel }}">{{ __('visa.request.destination') }}</label>
                    <select name="destination_country" id="destination_country" required class="field mt-2">
                        <option value="">{{ __('visa.request.destination_placeholder') }}</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country['code'] }}" @selected(old('destination_country') === $country['code'])>
                                {{ $country['flag'] }} {{ $country['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('destination_country')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                <div class="min-w-0">
                    <label for="travel_from" class="{{ $fieldLabel }}">{{ __('visa.request.travel_from') }}</label>
                    <input type="date" name="travel_from" id="travel_from" value="{{ old('travel_from') }}" required class="field mt-2">
                    @error('travel_from')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                <div class="min-w-0">
                    <label for="travel_to" class="{{ $fieldLabel }}">{{ __('visa.request.travel_to') }}</label>
                    <input type="date" name="travel_to" id="travel_to" value="{{ old('travel_to') }}" required class="field mt-2">
                    @error('travel_to')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                <div class="min-w-0">
                    <label for="applicants" class="{{ $fieldLabel }}">{{ __('visa.request.applicants') }}</label>
                    <input type="number" name="applicants" id="applicants" value="{{ old('applicants', 1) }}" min="1" max="{{ $maxApplicants }}" required class="field mt-2">
                    @error('applicants')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>
            </div>

            @guest
                <div class="mt-5 border-t border-border pt-5">
                    <h2 class="{{ $labelClass }}">{{ __('visa.request.section_contact') }}</h2>

                    <div class="mt-3 grid gap-5 md:grid-cols-2">
                        <div class="min-w-0">
                            <label for="guest_name" class="{{ $fieldLabel }}">{{ __('visa.request.your_name') }}</label>
                            <div class="relative mt-2">
                                <x-lucide name="user" :size="18" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-subtle" />
                                <input type="text" name="guest_name" id="guest_name" value="{{ old('guest_name') }}" required class="field field-icon">
                            </div>
                            @error('guest_name')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="guest_email" class="{{ $fieldLabel }}">{{ __('visa.request.your_email') }}</label>
                            <div class="relative mt-2">
                                <x-lucide name="mail" :size="18" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-subtle" />
                                <input type="email" name="guest_email" id="guest_email" value="{{ old('guest_email') }}" required class="field field-icon">
                            </div>
                            @error('guest_email')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            @endguest

            <div class="mt-5 flex flex-col gap-5 border-t border-border pt-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="consent" value="1" required class="mt-0.5 h-[18px] w-[18px] shrink-0 rounded border-placeholder text-primary focus:ring-primary">
                        <span class="max-w-2xl text-sm leading-relaxed text-muted">{{ __('visa.request.consent') }}</span>
                    </label>
                    @error('consent')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn btn-primary shrink-0 lg:min-w-[12rem]">
                    {{ __('visa.request.submit') }}
                    <x-lucide name="arrow-right" :size="18" />
                </button>
            </div>
        </form>

        <p class="mt-8 border-t border-placeholder pt-5 text-xs leading-relaxed text-muted">
            {{ __('visa.request.secure_note') }}
        </p>
    </section>

    <x-how-it-works
        :heading="__('visa.request.works_heading')"
        :sub="__('visa.request.works_sub')"
        :steps="[
            ['icon' => 'map-pin', 'title' => __('visa.request.works_1_title'), 'body' => __('visa.request.works_1_body')],
            ['icon' => 'mail', 'title' => __('visa.request.works_2_title'), 'body' => __('visa.request.works_2_body')],
            ['icon' => 'scale', 'title' => __('visa.request.works_3_title'), 'body' => __('visa.request.works_3_body')],
            ['icon' => 'circle-check', 'title' => __('visa.request.works_4_title'), 'body' => __('visa.request.works_4_body')],
        ]"
    />

    <x-partners-strip :partners="$partners" :heading="__('visa.request.partners_heading')" marquee />
@endsection
