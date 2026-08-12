@extends('layouts.app')

@section('title', __('auto_insurance.request.heading') . ' — Findex')

@php
    $steps = collect([1, 2, 3])->map(fn (int $n) => [
        'title' => __("auto_insurance.request.step_{$n}_title"),
        'body' => __("auto_insurance.request.step_{$n}_body"),
    ]);
@endphp

@section('content')
    <x-request-hero
        :badge="__('auto_insurance.request.badge')"
        :heading="__('auto_insurance.request.heading')"
        :subheading="__('auto_insurance.request.subheading')"
        :steps="$steps"
    />

    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
        <form
            method="POST"
            action="{{ route('insurance.auto.request.store') }}"
            class="space-y-8 rounded-2xl border border-placeholder p-6 shadow-sm sm:p-8"
            novalidate
        >
            @csrf

            {{-- Honeypot: hidden from real visitors, a bot filling every field trips it (see AutoInsuranceController::store) --}}
            <div class="hidden" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>

            {{-- Vehicle & owner --}}
            <div>
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('auto_insurance.request.section_vehicle') }}</p>

                <div class="mt-4">
                    <x-form-input name="vehicle_plate" :label="__('auto_insurance.request.vehicle_plate')" :placeholder="__('auto_insurance.request.vehicle_plate_placeholder')" required />
                </div>

                <div class="mt-4">
                    <x-form-input name="owner_id_number" :label="__('auto_insurance.request.owner_id_number')" :value="old('owner_id_number')" required />
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-ink">{{ __('auto_insurance.request.contract_term') }}</label>
                    @error('contract_term_months')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-2 grid grid-cols-3 gap-2">
                        @foreach ($contractTerms as $term)
                            <label class="group cursor-pointer">
                                <input type="radio" name="contract_term_months" value="{{ $term }}" class="peer sr-only" @checked((int) old('contract_term_months', 12) === $term) required>
                                <span class="flex items-center justify-center rounded-xl border border-border-muted px-3 py-3 text-center text-sm font-medium text-ink transition peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 group-hover:border-primary/60">
                                    {{ __('auto_insurance.request.contract_terms.' . $term) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            @guest
                <div class="border-t border-placeholder pt-6">
                    <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('auto_insurance.request.section_details') }}</p>

                    <div class="mt-4 space-y-4">
                        <x-form-input name="guest_name" :label="__('tourism.request.your_name')" required />
                        <x-form-input type="email" name="guest_email" :label="__('tourism.request.your_email')" required />
                    </div>
                </div>
            @endguest

            <div class="border-t border-placeholder pt-6">
                <label class="flex items-start gap-2 text-sm text-ink">
                    <input type="checkbox" name="consent" value="1" required class="mt-0.5 rounded border-border-muted text-primary focus:ring-primary">
                    <span>{{ __('auto_insurance.request.consent') }}</span>
                </label>
                @error('consent')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-6 w-full bg-primary px-6 py-3 text-sm font-medium text-white transition hover:bg-primary-dark sm:w-auto">
                    {{ __('auto_insurance.request.submit') }}
                </button>
            </div>
        </form>
    </section>
@endsection
