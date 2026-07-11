@extends('layouts.app')

@section('title', __('tourism.request.heading') . ' — Findex')

@php
    $flags = [
        'AE' => '🇦🇪', 'EG' => '🇪🇬', 'GE' => '🇬🇪', 'GR' => '🇬🇷',
        'TH' => '🇹🇭', 'CY' => '🇨🇾', 'IT' => '🇮🇹', 'FR' => '🇫🇷', 'ES' => '🇪🇸',
    ];

    $steps = [
        ['title' => __('tourism.request.step_1_title'), 'body' => __('tourism.request.step_1_body'), 'color' => 'slide-green'],
        ['title' => __('tourism.request.step_2_title'), 'body' => __('tourism.request.step_2_body'), 'color' => 'slide-blue'],
        ['title' => __('tourism.request.step_3_title'), 'body' => __('tourism.request.step_3_body'), 'color' => 'accent-yellow'],
    ];
@endphp

@section('content')
    {{-- Hero --}}
    <section class="border-b border-placeholder bg-primary/5">
        <div class="mx-auto max-w-3xl px-6 py-16 text-center lg:px-10">
            <span class="inline-flex rounded-full bg-slide-green/20 px-4 py-2 text-sm font-medium text-ink">
                {{ __('tourism.request.badge') }}
            </span>

            <h1 class="mt-6 font-heading text-3xl leading-tight font-bold text-ink sm:text-4xl">{{ __('tourism.request.heading') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-muted">{{ __('tourism.request.subheading') }}</p>

            <div class="mx-auto mt-10 grid grid-cols-1 gap-4 text-left sm:grid-cols-3">
                @foreach ($steps as $i => $step)
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-placeholder/60">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/20 font-heading text-xs font-bold" style="color: var(--color-{{ $step['color'] }})">
                            {{ $i + 1 }}
                        </span>
                        <p class="mt-3 text-sm font-semibold text-ink">{{ $step['title'] }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-muted">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
        <form method="POST" action="{{ route('tourism.request.store') }}" class="space-y-8 rounded-2xl border border-placeholder p-6 shadow-sm sm:p-8">
            @csrf

            {{-- Honeypot: hidden from real visitors, a bot filling every field trips it (see QuoteRequestController::store) --}}
            <div class="hidden" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>

            {{-- Trip --}}
            <div>
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('tourism.request.section_trip') }}</p>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.request.destination') }}</label>

                    @error('destination_country')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-5">
                        @foreach ($destinations as $code)
                            <label class="group cursor-pointer">
                                <input
                                    type="radio"
                                    name="destination_country"
                                    value="{{ $code }}"
                                    class="peer sr-only"
                                    @checked(old('destination_country') === $code)
                                    required
                                >
                                <span class="flex flex-col items-center gap-1 rounded-xl border border-border-muted px-2 py-3 text-center transition peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 group-hover:border-primary/60">
                                    <span class="text-2xl leading-none">{{ $flags[$code] }}</span>
                                    <span class="text-xs font-medium text-ink">{{ __('destinations.' . $code) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5">
                    <x-form-input
                        name="hotel_name"
                        :label="__('tourism.request.hotel_name')"
                        :placeholder="__('tourism.request.hotel_name_placeholder')"
                    />
                </div>

                <div class="mt-5 grid grid-cols-2 gap-4">
                    <x-form-input type="date" name="check_in" :label="__('tourism.request.check_in')" required />
                    <x-form-input type="date" name="check_out" :label="__('tourism.request.check_out')" required />
                </div>
            </div>

            {{-- Preferences --}}
            <div class="border-t border-placeholder pt-6">
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('tourism.request.section_preferences') }}</p>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <x-form-input type="number" name="adults" :label="__('tourism.request.adults')" :value="old('adults', 2)" min="1" max="20" required />
                    <x-form-input type="number" name="children" :label="__('tourism.request.children')" :value="old('children', 0)" min="0" max="20" />
                </div>

                <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="all_inclusive" value="1" @checked(old('all_inclusive')) class="rounded border-border-muted text-primary focus:ring-primary">
                        {{ __('tourism.request.all_inclusive') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="insurance" value="1" @checked(old('insurance')) class="rounded border-border-muted text-primary focus:ring-primary">
                        {{ __('tourism.request.insurance') }}
                    </label>
                </div>

                <div class="mt-4">
                    <label for="notes" class="block text-sm font-medium text-ink">{{ __('tourism.request.notes') }}</label>
                    <textarea
                        name="notes"
                        id="notes"
                        rows="3"
                        placeholder="{{ __('tourism.request.notes_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('notes') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @guest
                <div class="border-t border-placeholder pt-6">
                    <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('tourism.request.section_details') }}</p>

                    <div class="mt-4 space-y-4">
                        <x-form-input name="guest_name" :label="__('tourism.request.your_name')" required />

                        <div>
                            <x-form-input type="email" name="guest_email" :label="__('tourism.request.your_email')" required />
                            <p class="mt-1.5 text-xs text-subtle">
                                {{ __('tourism.request.your_email_hint') }}
                                <a href="{{ route('tourism.resend') }}" class="text-primary hover:underline">{{ __('tourism.resend.heading') }}</a>
                            </p>
                        </div>
                    </div>
                </div>
            @endguest

            <div class="border-t border-placeholder pt-6">
                <label class="flex items-start gap-2 text-sm text-ink">
                    <input type="checkbox" name="consent" value="1" required class="mt-0.5 rounded border-border-muted text-primary focus:ring-primary">
                    <span>{{ __('tourism.request.consent') }}</span>
                </label>
                @error('consent')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-6 w-full bg-primary px-6 py-3 text-sm font-medium text-white transition hover:bg-primary-dark sm:w-auto">
                    {{ __('tourism.request.submit') }}
                </button>
            </div>
        </form>
    </section>
@endsection
