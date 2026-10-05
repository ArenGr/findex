@extends('layouts.app')

@section('title', __('visa.request.heading') . ' — Findex')

@php
    // The insurance panel, label and field - one design across every vertical.
    $labelClass = 'block text-sm font-bold tracking-[0.14em] text-muted uppercase';
    $fieldLabel = 'block text-sm font-semibold text-ink';
    $error = 'mt-2 text-sm text-accent-red';
@endphp

@section('content')
    <x-section-hero
        tone="travel"
        :eyebrow="__('visa.request.eyebrow')"
        :title="__('visa.request.heading')"
        :subtitle="__('visa.request.subheading')"
    />

    <section class="py-8 sm:py-14">
        <div class="site-container">
            @if (session('status') === 'email-verification-required')
                <div class="mb-6 rounded-xl border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3.5 text-base text-ink">
                    {{ __('auth.verify_email.action_blocked') }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('visa.request.store') }}"
                novalidate
                {{-- White on white: a heavier border and a soft shadow lift the form off the page. --}}
                class="form-lg w-full overflow-hidden rounded-3xl border-2 border-border bg-surface shadow-[0_18px_44px_-28px_rgb(24_29_18/0.45)]"
                x-data="@js([
                    'destination' => old('destination_country', ''),
                    'from' => old('travel_from', ''),
                    'to' => old('travel_to', ''),
                    'name' => old('guest_name', ''),
                    'email' => old('guest_email', ''),
                ])"
            >
                @csrf

                {{-- Honeypot: hidden from real visitors, so anything here is a bot. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
                </div>

                {{-- The form keeps a readable measure; the rail beside it says what is coming. --}}
                <div class="grid lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <div class="p-5 sm:p-8 lg:p-10">
                        <h2 class="{{ $labelClass }}">{{ __('visa.request.section_trip') }}</h2>

                        <div class="mt-5 grid gap-6 sm:grid-cols-2">
                            <div class="min-w-0 sm:col-span-2">
                                <label for="destination_country" class="{{ $fieldLabel }}">{{ __('visa.request.destination') }}</label>
                                <select name="destination_country" id="destination_country" x-model="destination" required class="field mt-2.5">
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
                                <input type="date" name="travel_from" id="travel_from" value="{{ old('travel_from') }}" x-model="from" required class="field mt-2.5">
                                @error('travel_from')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>

                            <div class="min-w-0">
                                <label for="travel_to" class="{{ $fieldLabel }}">{{ __('visa.request.travel_to') }}</label>
                                <input type="date" name="travel_to" id="travel_to" value="{{ old('travel_to') }}" x-model="to" required class="field mt-2.5">
                                @error('travel_to')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>

                            <div class="min-w-0">
                                <label for="applicants" class="{{ $fieldLabel }}">{{ __('visa.request.applicants') }}</label>
                                <input type="number" name="applicants" id="applicants" value="{{ old('applicants', 1) }}" min="1" max="{{ $maxApplicants }}" required class="field mt-2.5">
                                @error('applicants')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        @guest
                            <div class="mt-8 border-t border-border pt-8">
                                <h2 class="{{ $labelClass }}">{{ __('visa.request.section_contact') }}</h2>

                                <div class="mt-5 grid gap-6 sm:grid-cols-2">
                                    <div class="min-w-0">
                                        <label for="guest_name" class="{{ $fieldLabel }}">{{ __('visa.request.your_name') }}</label>
                                        <div class="relative mt-2.5">
                                            <x-lucide name="user" :size="20" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-subtle" />
                                            <input type="text" name="guest_name" id="guest_name" value="{{ old('guest_name') }}" x-model="name" autocomplete="name" required class="field field-icon">
                                        </div>
                                        @error('guest_name')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="min-w-0">
                                        <label for="guest_email" class="{{ $fieldLabel }}">{{ __('visa.request.your_email') }}</label>
                                        <div class="relative mt-2.5">
                                            <x-lucide name="mail" :size="20" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-subtle" />
                                            <input type="email" name="guest_email" id="guest_email" value="{{ old('guest_email') }}" x-model="email" autocomplete="email" required class="field field-icon">
                                        </div>
                                        @error('guest_email')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        @endguest

                        <div class="mt-8 border-t border-border pt-6">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" name="consent" value="1" required @checked(old('consent')) class="mt-0.5 h-5 w-5 shrink-0 rounded border-placeholder text-primary focus:ring-primary">
                                <span class="text-base leading-relaxed text-muted">{{ __('visa.request.consent') }}</span>
                            </label>
                            @error('consent')<p class="{{ $error }}">{{ $message }}</p>@enderror

                            <div class="mt-6 flex justify-end">
                                <button type="submit" class="btn btn-primary min-w-[12rem]">
                                    {{ __('visa.request.submit') }}
                                    <x-lucide name="arrow-right" :size="18" />
                                </button>
                            </div>
                        </div>
                    </div>

                    @include('visa._rail')
                </div>
            </form>
        </div>
    </section>

    <x-how-it-works
        tone="travel"
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
