@extends('layouts.app')

@section('title', __('auto_insurance.request.heading') . ' — Findex')

@php
    // Icon + accent per step, in order. Titles/bodies come from translations.
    $steps = collect([1, 2, 3])->map(fn (int $n) => [
        'title' => __("auto_insurance.request.step_{$n}_title"),
        'body' => __("auto_insurance.request.step_{$n}_body"),
    ]);

    // Reusable input shells so every field matches (icon well + focus ring),
    // stated once rather than per field below.
    $inputClass = 'h-12 w-full rounded-lg border border-border-muted bg-white pl-11 pr-4 text-sm text-ink outline-none transition placeholder:text-subtle/70 hover:border-primary/60 focus:border-primary focus:ring-2 focus:ring-primary/15';
    $labelClass = 'mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted';

    // The site's card: rounded-2xl, a light border, shadow-sm - the same one
    // the home page uses everywhere.
    $cardClass = 'rounded-2xl border border-placeholder bg-white shadow-sm';

    $iconDisc = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary';
@endphp

@section('content')
    {{-- Hero geometry lives in x-page-hero, shared by every main page. tuck
         leaves room for the steps card to sit in the hero's bottom edge. --}}
    <x-page-hero
        :title="__('auto_insurance.request.heading')"
        :subtitle="__('auto_insurance.request.subheading')"
    >
        <x-slot:eyebrow>
            <x-hero-badge>
                <x-slot:icon>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </x-slot:icon>
                {{ __('auto_insurance.request.badge') }}
            </x-hero-badge>
        </x-slot:eyebrow>


        {{-- Green shield and car: this page's identity, now that the tint is
             shared. See public/images/insurance/hero-car-ins.png. --}}
        {{-- Three short steps, inside the hero: the page says what it is and
             where you are in one block. --}}
        <x-slot:steps>
            <x-hero-steps :steps="$steps->all()" :current="1" />
        </x-slot:steps>

        <x-slot:illustration>
                {{-- WebP, not the PNG source beside it: the PNG is 1536x1024 and
                     1.7 MB for a decorative illustration that never paints larger
                     than 315x210, and it was saturating the connection at exactly
                     the moment the heading fonts were trying to arrive - which is
                     what made the headings on this page visibly re-render. Same
                     png-source/webp-served split as public/images/services.
                     ?v=mtime because these are plain public/ files, not run
                     through Vite's hashed asset pipeline. --}}
                <img
                    src="{{ asset('images/insurance/hero-car-ins.webp') }}?v={{ filemtime(public_path('images/insurance/hero-car-ins.webp')) }}"
                    alt=""
                    width="630"
                    height="420"
                >
        </x-slot:illustration>
    </x-page-hero>

    <div class="site-container">
        {{-- ── Form + sidebar ───────────────────────────────────────────── --}}
        <section class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,1fr)_300px]">

            {{-- Main form (wider than the sidebar) --}}
            <form
                method="POST"
                action="{{ route('insurance.auto.request.store') }}"
                class="{{ $cardClass }} p-5 lg:p-7"
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
                    <div class="mb-6 rounded-lg border border-accent-red/30 bg-accent-red/5 px-4 py-3 text-sm text-accent-red">{{ $message }}</div>
                @enderror

                {{-- ── Vehicle & Policy Details ── --}}
                <div class="mb-6 flex items-center gap-3">
                    <span class="{{ $iconDisc }}">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 17h14M6 17l-.8-4.2A3 3 0 0 1 8.1 9h7.8a3 3 0 0 1 2.9 3.8L18 17"/><path d="M7 9l1-3a2 2 0 0 1 2-1.5h4A2 2 0 0 1 16 6l1 3"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>
                    </span>
                    <h2 class="text-[18px] font-semibold text-ink">{{ __('auto_insurance.request.section_vehicle') }}</h2>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    {{-- Plate --}}
                    <div>
                        <label for="vehicle_plate" class="{{ $labelClass }}">
                            {{ __('auto_insurance.request.vehicle_plate') }}
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-subtle">
                                <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10h2M7 14h6"/></svg>
                            </span>
                            <input type="text" name="vehicle_plate" id="vehicle_plate" value="{{ old('vehicle_plate') }}"
                                placeholder="{{ __('auto_insurance.request.vehicle_plate_placeholder') }}" required
                                class="{{ $inputClass }} uppercase placeholder:normal-case">
                        </div>
                        @error('vehicle_plate')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                    </div>

                    {{-- Owner ID --}}
                    <div>
                        <label for="owner_id_number" class="{{ $labelClass }}">
                            {{ __('auto_insurance.request.owner_id_number') }}
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-subtle">
                                <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            <input type="text" name="owner_id_number" id="owner_id_number" value="{{ old('owner_id_number') }}" required class="{{ $inputClass }}">
                        </div>
                        @error('owner_id_number')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Contract term — selectable cards, 12 months default --}}
                <div class="mt-6">
                    <label class="{{ $labelClass }}">{{ __('auto_insurance.request.contract_term') }}</label>
                    {{-- The chosen term carries a filled tick as well as the green
                         fill, so the selection does not rest on colour alone.

                         The tick is driven from the outer span with a nested
                         selector, not with peer-checked on the tick itself:
                         peer-* compiles to `.peer:checked ~ &`, which only matches
                         a *sibling* of the input. The tick is a descendant, so the
                         original `peer-checked:flex` on it never matched and no
                         term has ever shown a tick. --}}
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($contractTerms as $term)
                            <label class="cursor-pointer">
                                <input type="radio" name="contract_term_months" value="{{ $term }}" class="peer sr-only" @checked((int) old('contract_term_months', 12) === $term) required>
                                <span class="relative flex h-12 items-center justify-center rounded-lg border border-border-muted text-sm text-muted transition hover:border-primary peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:font-semibold peer-checked:text-primary peer-checked:[&>.term-tick]:flex peer-focus-visible:ring-2 peer-focus-visible:ring-primary/30">
                                    {{ __('auto_insurance.request.contract_terms.' . $term) }}
                                    <span class="term-tick absolute right-3 hidden h-5 w-5 items-center justify-center rounded-full bg-primary text-white">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('contract_term_months')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                </div>

                <div class="my-7 border-t border-placeholder"></div>

                {{-- ── Contact & Payout Details ── --}}
                <div class="mb-1 flex items-center gap-3">
                    <span class="{{ $iconDisc }}">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"/><rect x="3" y="4" width="18" height="18" rx="2"/><circle cx="12" cy="10" r="2"/></svg>
                    </span>
                    <h2 class="text-[18px] font-semibold text-ink">{{ __('auto_insurance.request.market_heading') }}</h2>
                </div>
                <p class="mb-6 ml-12 text-xs leading-5 text-muted">{{ __('auto_insurance.request.market_explainer') }}</p>

                @guest
                    <div class="mb-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="guest_name" class="{{ $labelClass }}">{{ __('tourism.request.your_name') }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-subtle">
                                    <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                                <input type="text" name="guest_name" id="guest_name" value="{{ old('guest_name') }}" required class="{{ $inputClass }}">
                            </div>
                            @error('guest_name')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="guest_email" class="{{ $labelClass }}">{{ __('tourism.request.your_email') }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-subtle">
                                    <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                                </span>
                                <input type="email" name="guest_email" id="guest_email" value="{{ old('guest_email') }}" required class="{{ $inputClass }}">
                            </div>
                            @error('guest_email')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endguest

                <div class="grid gap-5 md:grid-cols-2">
                    {{-- Phone --}}
                    <div>
                        <label for="market_phone" class="{{ $labelClass }}">{{ __('auto_insurance.request.market_phone') }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-subtle">
                                <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1L7.6 9.9a16 16 0 0 0 6 6l1.4-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6A2 2 0 0 1 22 16.9z"/></svg>
                            </span>
                            <input type="tel" name="market_phone" id="market_phone" value="{{ old('market_phone') }}" required class="{{ $inputClass }}">
                        </div>
                        @error('market_phone')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="market_email" class="{{ $labelClass }}">{{ __('auto_insurance.request.market_email') }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-subtle">
                                <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                            </span>
                            <input type="email" name="market_email" id="market_email" value="{{ old('market_email') }}" required class="{{ $inputClass }}">
                        </div>
                        @error('market_email')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Bank account (half width on desktop) --}}
                <div class="mt-5 md:max-w-[calc(50%-0.625rem)]">
                    <label for="market_bank_account" class="{{ $labelClass }}">{{ __('auto_insurance.request.market_bank_account') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-subtle">
                            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="22" x2="21" y2="22"/><line x1="6" y1="18" x2="6" y2="11"/><line x1="10" y1="18" x2="10" y2="11"/><line x1="14" y1="18" x2="14" y2="11"/><line x1="18" y1="18" x2="18" y2="11"/><polygon points="12 2 20 7 4 7"/></svg>
                        </span>
                        <input type="text" name="market_bank_account" id="market_bank_account" value="{{ old('market_bank_account') }}" required class="{{ $inputClass }}">
                    </div>
                    @error('market_bank_account')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                    <p class="mt-2 text-[11px] leading-4 text-muted">{{ __('auto_insurance.request.market_bank_account_hint') }}</p>
                </div>

                <div class="my-7 border-t border-placeholder"></div>

                {{-- Consent (left) + submit (bottom-right) --}}
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="consent" value="1" required class="mt-0.5 h-[17px] w-[17px] rounded border-border-muted text-primary focus:ring-primary">
                            <span class="max-w-[620px] text-[13px] leading-5 text-body-text">{{ __('auto_insurance.request.consent') }}</span>
                        </label>
                        @error('consent')<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
                    </div>

                    <button
                        type="submit" :disabled="loading"
                        class="btn btn-primary min-w-[175px] shrink-0 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                        </svg>
                        <span x-text="loading ? '{{ __('auto_insurance.loading.title') }}' : '{{ __('auto_insurance.request.submit') }}'">{{ __('auto_insurance.request.submit') }}</span>
                        <svg x-show="!loading" class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>
            </form>

            {{-- Context sidebar --}}
            <aside class="space-y-4">
                {{-- Secure & private (highlighted light-green) --}}
                <div class="rounded-2xl border border-primary/20 bg-primary/5 p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1.5"/></svg>
                        </span>
                        <div>
                            <h3 class="font-heading text-[15px] font-semibold text-primary">{{ __('auto_insurance.request.aside_secure_title') }}</h3>
                            <p class="mt-2 text-[13px] leading-5 text-muted">{{ __('auto_insurance.request.aside_secure_body') }}</p>
                        </div>
                    </div>
                </div>

                {{-- What happens next? --}}
                <div class="{{ $cardClass }} p-5">
                    <h3 class="font-heading text-[15px] font-semibold text-ink">{{ __('auto_insurance.request.aside_next_title') }}</h3>
                    <div class="mt-5 space-y-4">
                        @foreach (['aside_next_1', 'aside_next_2', 'aside_next_3'] as $item)
                            <div class="flex gap-3">
                                <span class="mt-0.5 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full bg-primary text-white">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                </span>
                                <p class="text-[13px] leading-5 text-ink">{{ __('auto_insurance.request.' . $item) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Why use Findex? --}}
                <div class="{{ $cardClass }} p-5">
                    <h3 class="font-heading text-[15px] font-semibold text-ink">{{ __('auto_insurance.request.why_title') }}</h3>
                    <div class="mt-5 space-y-5">
                        @foreach ([
                            ['why_1_title', 'why_1_body', '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>'],
                            ['why_2_title', 'why_2_body', '<line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
                            ['why_3_title', 'why_3_body', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>'],
                        ] as [$title, $body, $icon])
                            <div class="flex gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                                </span>
                                <div>
                                    <div class="text-[13px] font-semibold text-ink">{{ __('auto_insurance.request.' . $title) }}</div>
                                    <div class="mt-0.5 text-xs leading-5 text-muted">{{ __('auto_insurance.request.' . $body) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>
        </section>
    </div>
@endsection
