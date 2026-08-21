@extends('layouts.app')

@section('title', __('auto_insurance.request.heading') . ' — Findex')

@php
    $steps = collect([1, 2, 3])->map(fn (int $n) => __("auto_insurance.request.step_{$n}_title"));
@endphp

@section('content')
    {{-- Auto insurance request, from the approved Stitch step-1 screen -
         rebuilt with Findex tokens and inline icons (no CDN Tailwind, Google
         fonts or Material Symbols), inside the app's own header and footer.
         The design's palette is our travel design system; rendered here with
         the standard Findex utilities so the flow keeps one palette. --}}
    <section class="mx-auto max-w-6xl px-6 py-12 lg:px-10 lg:py-16">
        {{-- Hero --}}
        <div class="mb-10">
            <span class="mb-4 inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M5 11l1.5-4.5A2 2 0 018.4 5h7.2a2 2 0 011.9 1.5L19 11m-14 0h14m-14 0a2 2 0 00-2 2v3a1 1 0 001 1h1a1 1 0 001-1v-1h10v1a1 1 0 001 1h1a1 1 0 001-1v-3a2 2 0 00-2-2M7 15h.01M17 15h.01"/>
                </svg>
                {{ __('auto_insurance.request.badge') }}
            </span>
            <h1 class="font-heading text-3xl font-bold text-ink lg:text-4xl">{{ __('auto_insurance.request.heading') }}</h1>
            <p class="mt-3 max-w-2xl text-base text-muted lg:text-lg">{{ __('auto_insurance.request.subheading') }}</p>
        </div>

        {{-- How it works (desktop) --}}
        <div class="relative mb-14 hidden max-w-3xl md:block">
            <div class="absolute left-0 right-0 top-4 h-0.5 bg-placeholder"></div>
            <div class="relative flex justify-between">
                @foreach ($steps as $i => $label)
                    <div class="flex flex-1 flex-col items-center gap-2 px-2 text-center">
                        <span @class([
                            'flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold',
                            'bg-primary text-white' => $i === 0,
                            'bg-placeholder text-muted' => $i !== 0,
                        ])>{{ $i + 1 }}</span>
                        <span @class([
                            'text-sm font-medium',
                            'text-primary' => $i === 0,
                            'text-muted' => $i !== 0,
                        ])>{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
            {{-- Form card --}}
            <form
                method="POST"
                action="{{ route('insurance.auto.request.store') }}"
                class="flex-1 rounded-3xl border border-placeholder bg-white p-6 shadow-[0px_4px_20px_rgba(0,0,0,0.05)] sm:p-8 lg:p-10"
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

                {{-- Honeypot: hidden from real visitors; a bot filling every field trips it (see AutoInsuranceController::store). --}}
                <div class="hidden" aria-hidden="true">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
                </div>

                @error('insurance_quote')
                    <div class="mb-6 rounded-xl border border-accent-red/30 bg-accent-red/5 px-4 py-3 text-sm text-accent-red">
                        {{ $message }}
                    </div>
                @enderror

                <div class="space-y-8">
                    {{-- Vehicle & owner --}}
                    <div class="space-y-5">
                        {{-- Plate --}}
                        <div>
                            <label for="vehicle_plate" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted">{{ __('auto_insurance.request.vehicle_plate') }}</label>
                            <input
                                type="text" name="vehicle_plate" id="vehicle_plate"
                                value="{{ old('vehicle_plate') }}"
                                placeholder="{{ __('auto_insurance.request.vehicle_plate_placeholder') }}"
                                required
                                class="h-14 w-full rounded-xl border border-border-muted bg-white px-4 text-base uppercase text-ink outline-none transition placeholder:text-subtle/60 placeholder:normal-case focus:border-primary focus:ring-1 focus:ring-primary"
                            >
                            @error('vehicle_plate')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                        </div>

                        {{-- Owner ID --}}
                        <div>
                            <label for="owner_id_number" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted">{{ __('auto_insurance.request.owner_id_number') }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-subtle">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </span>
                                <input
                                    type="text" name="owner_id_number" id="owner_id_number"
                                    value="{{ old('owner_id_number') }}"
                                    required
                                    class="h-14 w-full rounded-xl border border-border-muted bg-white pl-12 pr-4 text-base text-ink outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
                                >
                            </div>
                            @error('owner_id_number')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                            <p class="mt-2 flex items-center gap-1.5 text-xs text-muted">
                                <svg class="h-3.5 w-3.5 shrink-0 text-primary" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                                {{ __('auto_insurance.request.data_secure_note') }}
                            </p>
                        </div>

                        {{-- Coverage period --}}
                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted">{{ __('auto_insurance.request.contract_term') }}</label>
                            <div class="grid grid-cols-3 gap-3">
                                @foreach ($contractTerms as $term)
                                    <label class="group cursor-pointer">
                                        <input type="radio" name="contract_term_months" value="{{ $term }}" class="peer sr-only" @checked((int) old('contract_term_months', 12) === $term) required>
                                        <span class="flex items-center justify-center rounded-xl border border-border-muted py-3.5 text-center text-sm font-medium text-muted transition peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 group-hover:border-primary/60">
                                            {{ __('auto_insurance.request.contract_terms.' . $term) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('contract_term_months')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @guest
                        <div class="border-t border-placeholder pt-6">
                            <p class="text-xs font-semibold uppercase tracking-wider text-subtle">{{ __('auto_insurance.request.section_details') }}</p>
                            <div class="mt-4 space-y-4">
                                <x-form-input name="guest_name" :label="__('tourism.request.your_name')" required />
                                <x-form-input type="email" name="guest_email" :label="__('tourism.request.your_email')" required />
                            </div>
                        </div>
                    @endguest

                    {{-- Contact & payout. Required: quotes come from the Bureau
                         calculator, which needs a phone, email and a bank
                         account whose code it recognises. None of it is stored
                         - see MarketQuoteDetails. --}}
                    <div class="border-t border-placeholder pt-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-subtle">{{ __('auto_insurance.request.market_heading') }}</p>
                        <p class="mt-1 text-xs text-muted">{{ __('auto_insurance.request.market_explainer') }}</p>
                        <div class="mt-4 space-y-4">
                            <x-form-input name="market_phone" type="tel" :label="__('auto_insurance.request.market_phone')" :value="old('market_phone')" required />
                            <x-form-input name="market_email" type="email" :label="__('auto_insurance.request.market_email')" :value="old('market_email')" required />
                            <div>
                                <x-form-input name="market_bank_account" :label="__('auto_insurance.request.market_bank_account')" :value="old('market_bank_account')" required />
                                <p class="mt-1.5 text-xs text-muted">{{ __('auto_insurance.request.market_bank_account_hint') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Consent --}}
                    <div class="border-t border-placeholder pt-6">
                        <label class="flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="consent" value="1" required class="mt-0.5 rounded border-border-muted text-primary focus:ring-primary">
                            <span>{{ __('auto_insurance.request.consent') }}</span>
                        </label>
                        @error('consent')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Submit --}}
                <div class="mt-8 flex justify-end border-t border-placeholder pt-6">
                    <button
                        type="submit"
                        :disabled="loading"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-8 py-4 text-sm font-medium text-white shadow-sm transition hover:bg-primary-dark active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70 md:w-auto"
                    >
                        <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                        </svg>
                        <span x-text="loading ? '{{ __('auto_insurance.loading.title') }}' : '{{ __('auto_insurance.request.submit') }}'">{{ __('auto_insurance.request.submit') }}</span>
                        <svg x-show="!loading" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </div>
            </form>

            {{-- Context sidebar --}}
            <aside class="w-full space-y-4 lg:w-80 lg:shrink-0">
                <div class="rounded-3xl border border-primary/15 bg-primary/5 p-6">
                    <h3 class="flex items-center gap-2 font-heading font-semibold text-primary">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path>
                        </svg>
                        {{ __('auto_insurance.request.aside_secure_title') }}
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted">{{ __('auto_insurance.request.aside_secure_body') }}</p>
                </div>

                <div class="rounded-3xl border border-placeholder bg-white p-6 shadow-[0px_4px_20px_rgba(0,0,0,0.05)]">
                    <h3 class="font-heading font-semibold text-ink">{{ __('auto_insurance.request.aside_next_title') }}</h3>
                    <ul class="mt-3 space-y-3">
                        @foreach (['aside_next_1', 'aside_next_2', 'aside_next_3'] as $item)
                            <li class="flex items-start gap-2.5 text-sm text-ink">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.2 14.5-3.5-3.5 1.4-1.4 2.1 2.1 4.6-4.6 1.4 1.4-6 6z"/>
                                </svg>
                                {{ __('auto_insurance.request.' . $item) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>
    </section>
@endsection
