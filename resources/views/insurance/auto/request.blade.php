@extends('layouts.app')

@section('title', __('auto_insurance.request.heading') . ' — Findex')

@php
    $steps = [
        ['title' => __('auto_insurance.request.step_1_title'), 'body' => __('auto_insurance.request.step_1_body'), 'color' => 'slide-green'],
        ['title' => __('auto_insurance.request.step_2_title'), 'body' => __('auto_insurance.request.step_2_body'), 'color' => 'slide-blue'],
        ['title' => __('auto_insurance.request.step_3_title'), 'body' => __('auto_insurance.request.step_3_body'), 'color' => 'accent-yellow'],
    ];

    $ownerIdLabels = [
        'individual' => __('auto_insurance.request.owner_id_number_individual'),
        'legal_entity' => __('auto_insurance.request.owner_id_number_legal_entity'),
    ];
@endphp

@section('content')
    {{-- Hero --}}
    <section class="border-b border-placeholder bg-primary/5">
        <div class="mx-auto max-w-3xl px-6 py-16 text-center lg:px-10">
            <span class="inline-flex rounded-full bg-slide-green/20 px-4 py-2 text-sm font-medium text-ink">
                {{ __('auto_insurance.request.badge') }}
            </span>

            <h1 class="mt-6 font-heading text-3xl leading-tight font-bold text-ink sm:text-4xl">{{ __('auto_insurance.request.heading') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-muted">{{ __('auto_insurance.request.subheading') }}</p>

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
        <form
            method="POST"
            action="{{ route('insurance.auto.request.store') }}"
            class="space-y-8 rounded-2xl border border-placeholder p-6 shadow-sm sm:p-8"
            x-data="{ ownerType: '{{ old('owner_type', 'individual') }}', idLabels: @js($ownerIdLabels) }"
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
                    <label class="block text-sm font-medium text-ink">{{ __('auto_insurance.request.owner_type') }}</label>
                    @error('owner_type')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-2 grid grid-cols-2 gap-2">
                        @foreach (['individual', 'legal_entity'] as $type)
                            <label class="group cursor-pointer">
                                <input type="radio" name="owner_type" value="{{ $type }}" x-model="ownerType" class="peer sr-only" @checked(old('owner_type', 'individual') === $type) required>
                                <span class="flex items-center justify-center rounded-xl border border-border-muted px-3 py-3 text-center text-sm font-medium text-ink transition peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 group-hover:border-primary/60">
                                    {{ __('auto_insurance.request.owner_types.' . $type) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4">
                    <label for="owner_id_number" class="block text-sm font-medium text-ink" x-text="idLabels[ownerType]"></label>
                    <input
                        type="text"
                        name="owner_id_number"
                        id="owner_id_number"
                        value="{{ old('owner_id_number') }}"
                        required
                        class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('owner_id_number') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                    >
                    @error('owner_id_number')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
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
