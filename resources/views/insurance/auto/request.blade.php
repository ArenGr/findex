@extends('layouts.app')

@section('title', __('auto_insurance.request.heading') . ' — Findex')

@php
    // The card's shared shapes, stated once.
    $eyebrow = 'text-xs font-bold tracking-[0.18em] text-primary uppercase';
    $heading = 'mt-2.5 font-heading text-3xl leading-tight font-bold text-ink outline-none sm:text-4xl';
    $sub = 'mt-3 text-base text-muted';
    $panelFooter = 'mt-8 flex items-center justify-between gap-3 border-t border-border pt-6';

    $isGuest = auth()->guest();
    // The visitor gives one email; it goes to the insurer and, for a guest, keeps the results link.
    $emailField = $isGuest ? 'guest_email' : 'market_email';

    // Which step a rejected submission should reopen: the earliest one holding a rejected field.
    $stepFields = [
        1 => ['vehicle_plate', 'owner_id_number', 'contract_term_months'],
        2 => ['guest_name', 'guest_email', 'market_email', 'market_phone'],
        3 => ['market_bank_account'],
        4 => ['consent'],
    ];
    $initialStep = 1;
    foreach ($stepFields as $stepNumber => $names) {
        if ($errors->hasAny($names)) {
            $initialStep = $stepNumber;
            break;
        }
    }
@endphp

@section('content')
    <x-section-hero
        tone="insurance"
        :eyebrow="__('nav.insurance.label')"
        :title="__('auto_insurance.request.heading')"
        :subtitle="__('auto_insurance.request.works_sub')"
    />

    <section class="py-8 sm:py-14">
        <div class="site-container">
            <div id="insurance-form-top" class="scroll-mt-24"></div>

            <form
                method="POST"
                action="{{ route('insurance.auto.request.store') }}"
                novalidate
                {{-- White on white: a heavier border and a soft shadow lift the form off the page. --}}
                class="form-lg w-full rounded-3xl border-2 border-border bg-surface p-5 shadow-[0_18px_44px_-28px_rgb(24_29_18/0.45)] sm:p-8 lg:p-10"
                x-data="autoInsuranceForm(@js([
                    'initialStep' => $initialStep,
                    'plate' => old('vehicle_plate', ''),
                    'term' => old('contract_term_months', 12),
                    'name' => old('guest_name', $isGuest ? '' : auth()->user()->name),
                    'email' => old($emailField, ''),
                    'phone' => old('market_phone', ''),
                    'bankAccount' => old('market_bank_account', ''),
                    'labels' => [
                        'notSet' => __('auto_insurance.request.wizard.not_set'),
                        'terms' => __('auto_insurance.request.contract_terms'),
                    ],
                ]))"
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

                @include('insurance.auto._wizard-stepper')

                @error('insurance_quote')
                    <div class="mt-7 rounded-xl border border-accent-red/30 bg-accent-red/5 px-4 py-3.5 text-base text-accent-red">{{ $message }}</div>
                @enderror

                {{-- STEP 1 - the vehicle --}}
                <div data-step="1" :class="{ 'block': step === 1, 'hidden': step !== 1 }" @class(['pt-8', 'hidden' => $initialStep !== 1])>
                    <p class="{{ $eyebrow }}">{{ __('auto_insurance.request.wizard.vehicle_eyebrow') }}</p>
                    <h2 x-ref="heading1" tabindex="-1" class="{{ $heading }}">{{ __('auto_insurance.request.wizard.vehicle_heading') }}</h2>
                    <p class="{{ $sub }}">{{ __('auto_insurance.request.wizard.vehicle_sub') }}</p>

                    <div class="mt-7 grid gap-6 md:grid-cols-2">
                        @include('insurance.auto._field', ['field' => [
                            'name' => 'vehicle_plate',
                            'type' => 'text',
                            'icon' => 'car',
                            'model' => 'plate',
                            'label' => __('auto_insurance.request.vehicle_plate'),
                            'placeholder' => __('auto_insurance.request.vehicle_plate_placeholder'),
                            'class' => 'uppercase placeholder:normal-case',
                        ]])

                        @include('insurance.auto._field', ['field' => [
                            'name' => 'owner_id_number',
                            'type' => 'text',
                            'icon' => 'id-card',
                            'label' => __('auto_insurance.request.owner_id_number'),
                        ]])

                        <fieldset class="md:col-span-2">
                            <legend class="block text-sm font-semibold text-ink">{{ __('auto_insurance.request.contract_term') }}</legend>
                            <div class="mt-2.5 grid grid-cols-3 gap-2.5 sm:max-w-lg">
                                @foreach ($contractTerms as $term)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="contract_term_months" value="{{ $term }}" x-model="term" class="peer sr-only" @checked((int) old('contract_term_months', 12) === $term) required>
                                        <span class="flex min-h-[3.5rem] items-center justify-center gap-1.5 rounded-lg border border-placeholder bg-white px-2 text-sm font-medium whitespace-nowrap text-muted transition sm:px-3 sm:text-base hover:border-primary hover:bg-primary/10 hover:text-primary peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-checked:hover:bg-primary-dark peer-focus-visible:ring-2 peer-focus-visible:ring-primary/30">
                                            {{ __('auto_insurance.request.contract_terms.' . $term) }}
                                            {{-- x-cloak, not a @if: a Blade directive inside a component tag stops it compiling. --}}
                                            <x-lucide name="circle-check" :size="18" x-show="term == {{ $term }}" x-cloak class="hidden sm:block" />
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('contract_term_months')<p class="mt-2 text-sm text-accent-red">{{ $message }}</p>@enderror
                        </fieldset>
                    </div>

                    <div class="{{ $panelFooter }}">
                        <p class="text-sm font-medium text-muted">{{ __('auto_insurance.request.wizard.step_of', ['current' => 1, 'total' => 4]) }}</p>
                        <button type="button" @click="next()" class="btn btn-primary min-w-[10rem]">
                            {{ __('auto_insurance.request.wizard.continue') }}
                            <x-lucide name="arrow-right" :size="18" />
                        </button>
                    </div>
                </div>

                {{-- STEP 2 - where the quotes go --}}
                <div data-step="2" :class="{ 'block': step === 2, 'hidden': step !== 2 }" @class(['pt-8', 'hidden' => $initialStep !== 2])>
                    <p class="{{ $eyebrow }}">{{ __('auto_insurance.request.wizard.contact_eyebrow') }}</p>
                    <h2 x-ref="heading2" tabindex="-1" class="{{ $heading }}">{{ __('auto_insurance.request.wizard.contact_heading') }}</h2>
                    <p class="{{ $sub }}">{{ __('auto_insurance.request.wizard.contact_sub') }}</p>

                    <div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @if ($isGuest)
                            @include('insurance.auto._field', ['field' => [
                                'name' => 'guest_name',
                                'type' => 'text',
                                'icon' => 'user',
                                'model' => 'name',
                                'autocomplete' => 'name',
                                'label' => __('tourism.request.your_name'),
                                'placeholder' => __('auto_insurance.request.wizard.name_placeholder'),
                            ]])
                        @endif

                        @include('insurance.auto._field', ['field' => [
                            'name' => $emailField,
                            'error_names' => [$isGuest ? 'market_email' : 'guest_email'],
                            'type' => 'email',
                            'icon' => 'mail',
                            'model' => 'email',
                            'autocomplete' => 'email',
                            'label' => __('auto_insurance.request.market_email'),
                            'placeholder' => __('auto_insurance.request.wizard.email_placeholder'),
                        ]])

                        @include('insurance.auto._field', ['field' => [
                            'name' => 'market_phone',
                            'type' => 'tel',
                            'icon' => 'phone',
                            'model' => 'phone',
                            'autocomplete' => 'tel',
                            'label' => __('auto_insurance.request.market_phone'),
                            'placeholder' => __('auto_insurance.request.wizard.phone_placeholder'),
                        ]])

                        {{-- The insurer is quoted through the bureau, which wants an address of its own. --}}
                        @if ($isGuest)
                            <input type="hidden" name="market_email" :value="email">
                        @endif
                    </div>

                    <p class="mt-6 flex items-start gap-2 text-sm leading-relaxed text-muted">
                        <x-lucide name="lock" :size="16" class="mt-0.5 text-primary" />
                        {{ __('auto_insurance.request.wizard.contact_privacy') }}
                    </p>

                    <div class="{{ $panelFooter }}">
                        <button type="button" @click="back()" class="btn btn-secondary">
                            <x-lucide name="arrow-left" :size="18" />
                            {{ __('auto_insurance.request.wizard.back') }}
                        </button>
                        <button type="button" @click="next()" class="btn btn-primary min-w-[10rem]">
                            {{ __('auto_insurance.request.wizard.continue') }}
                            <x-lucide name="arrow-right" :size="18" />
                        </button>
                    </div>
                </div>

                {{-- STEP 3 - the account the bureau asks for --}}
                <div data-step="3" :class="{ 'block': step === 3, 'hidden': step !== 3 }" @class(['pt-8', 'hidden' => $initialStep !== 3])>
                    <p class="{{ $eyebrow }}">{{ __('auto_insurance.request.wizard.payout_eyebrow') }}</p>
                    <h2 x-ref="heading3" tabindex="-1" class="{{ $heading }}">{{ __('auto_insurance.request.wizard.payout_heading') }}</h2>
                    <p class="{{ $sub }}">{{ __('auto_insurance.request.wizard.payout_sub') }}</p>

                    <div class="mt-7 grid gap-6 lg:grid-cols-2 lg:items-start">
                        @include('insurance.auto._field', ['field' => [
                            'name' => 'market_bank_account',
                            'type' => 'text',
                            'icon' => 'landmark',
                            'model' => 'bankAccount',
                            'input' => 'bankAccount = bankAccount.replace(/\D/g, \'\')',
                            'inputmode' => 'numeric',
                            'maxlength' => 16,
                            'label' => __('auto_insurance.request.market_bank_account'),
                            'placeholder' => __('auto_insurance.request.wizard.bank_placeholder'),
                        ]])

                        <div class="flex items-start gap-3.5 rounded-2xl border border-border bg-surface-alt px-5 py-4 lg:mt-8">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <x-lucide name="lock" :size="18" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink">{{ __('auto_insurance.request.wizard.payout_private_title') }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-muted">{{ __('auto_insurance.request.wizard.payout_private_body') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="{{ $panelFooter }}">
                        <button type="button" @click="back()" class="btn btn-secondary">
                            <x-lucide name="arrow-left" :size="18" />
                            {{ __('auto_insurance.request.wizard.back') }}
                        </button>
                        <button type="button" @click="next()" class="btn btn-primary min-w-[10rem]">
                            {{ __('auto_insurance.request.wizard.continue') }}
                            <x-lucide name="arrow-right" :size="18" />
                        </button>
                    </div>
                </div>

                {{-- STEP 4 - what we are about to send --}}
                <div data-step="4" :class="{ 'block': step === 4, 'hidden': step !== 4 }" @class(['pt-8', 'hidden' => $initialStep !== 4])>
                    <p class="{{ $eyebrow }}">{{ __('auto_insurance.request.wizard.review_eyebrow') }}</p>
                    <h2 x-ref="heading4" tabindex="-1" class="{{ $heading }}">{{ __('auto_insurance.request.wizard.review_heading') }}</h2>
                    <p class="{{ $sub }}">{{ __('auto_insurance.request.wizard.review_sub') }}</p>

                    <ul class="mt-7 divide-y divide-border rounded-2xl border border-border">
                        @foreach ([
                            ['icon' => 'car', 'label' => __('auto_insurance.request.wizard.review_vehicle'), 'value' => 'plateSummary', 'step' => 1],
                            ['icon' => 'clock', 'label' => __('auto_insurance.request.wizard.review_term'), 'value' => 'termSummary', 'step' => 1],
                            ['icon' => 'user', 'label' => __('auto_insurance.request.wizard.review_contact'), 'value' => 'contactSummary', 'step' => 2, 'lead' => 'name'],
                            ['icon' => 'landmark', 'label' => __('auto_insurance.request.wizard.review_bank'), 'value' => 'bankSummary', 'step' => 3],
                        ] as $row)
                            {{-- Label over value on a phone, side by side once there is room. --}}
                            <li class="flex items-start gap-3.5 px-5 py-4 sm:items-center sm:gap-4">
                                <x-lucide :name="$row['icon']" :size="20" class="mt-0.5 shrink-0 text-subtle sm:mt-0" />
                                <div class="min-w-0 flex-1 sm:flex sm:items-center sm:gap-4">
                                    <span class="block text-sm text-muted sm:w-32 sm:shrink-0">{{ $row['label'] }}</span>
                                    <div class="min-w-0 sm:flex-1">
                                        @isset($row['lead'])
                                            <p class="text-base font-semibold break-words text-ink" x-text="{{ $row['lead'] }} || labels.notSet"></p>
                                        @endisset
                                        <p @class(['break-words', 'text-base font-semibold text-ink' => ! isset($row['lead']), 'text-sm text-muted' => isset($row['lead'])]) x-text="{{ $row['value'] }}"></p>
                                    </div>
                                </div>
                                <button type="button" @click="goToStep({{ $row['step'] }})" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-1 text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none">
                                    <x-lucide name="pencil" :size="16" />
                                    {{ __('auto_insurance.request.wizard.review_edit') }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-6">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="consent" value="1" required @checked(old('consent')) class="mt-0.5 h-5 w-5 shrink-0 rounded border-placeholder text-primary focus:ring-primary">
                            <span class="text-base leading-relaxed text-muted">{{ __('auto_insurance.request.consent') }}</span>
                        </label>
                        @error('consent')<p class="mt-2 text-sm text-accent-red">{{ $message }}</p>@enderror
                    </div>

                    <div class="{{ $panelFooter }}">
                        <button type="button" @click="back()" class="btn btn-secondary">
                            <x-lucide name="arrow-left" :size="18" />
                            {{ __('auto_insurance.request.wizard.back') }}
                        </button>
                        <button type="submit" :disabled="loading" class="btn btn-primary min-w-[12rem]">
                            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                            </svg>
                            <span x-text="loading ? @js(__('auto_insurance.loading.title')) : @js(__('auto_insurance.request.submit'))">{{ __('auto_insurance.request.submit') }}</span>
                            <x-lucide x-show="!loading" name="arrow-right" :size="18" />
                        </button>
                    </div>

                    <p class="mt-5 text-right text-sm text-muted">
                        {{ trans_choice('compare_ui.insurers', $insurerCount, ['count' => $insurerCount]) }}
                        · {{ __('auto_insurance.request.signal_free') }}
                        · {{ __('auto_insurance.request.wizard.footnote_fast') }}
                    </p>
                </div>
            </form>
        </div>
    </section>

    <x-partners-strip :partners="$partners" :heading="__('auto_insurance.request.partners_heading')" marquee />
@endsection
