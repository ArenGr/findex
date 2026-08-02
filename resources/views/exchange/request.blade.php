@extends('layouts.app')

@section('title', __('exchange_quotes.request.heading') . ' — Findex')

@php
    $steps = [
        ['title' => __('exchange_quotes.request.step_1_title'), 'body' => __('exchange_quotes.request.step_1_body'), 'color' => 'slide-green'],
        ['title' => __('exchange_quotes.request.step_2_title'), 'body' => __('exchange_quotes.request.step_2_body'), 'color' => 'slide-blue'],
        ['title' => __('exchange_quotes.request.step_3_title'), 'body' => __('exchange_quotes.request.step_3_body'), 'color' => 'accent-yellow'],
    ];

    // Both direction labels, pre-translated per currency, so switching the
    // currency select client-side can relabel the direction options without
    // a round trip - the :currency placeholder can't be filled in until a
    // currency is actually chosen.
    $directionLabels = $currencies->mapWithKeys(fn ($currency) => [
        $currency->code => [
            'buy_rate' => __('exchange_quotes.request.direction_buy_rate', ['currency' => $currency->code]),
            'sell_rate' => __('exchange_quotes.request.direction_sell_rate', ['currency' => $currency->code]),
        ],
    ]);
@endphp

@section('content')
    {{-- Hero --}}
    <section class="border-b border-placeholder bg-primary/5">
        <div class="mx-auto max-w-3xl px-6 py-16 text-center lg:px-10">
            <span class="inline-flex rounded-full bg-slide-green/20 px-4 py-2 text-sm font-medium text-ink">
                {{ __('exchange_quotes.request.badge') }}
            </span>

            <h1 class="mt-6 font-heading text-3xl leading-tight font-bold text-ink sm:text-4xl">{{ __('exchange_quotes.request.heading') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-muted">{{ __('exchange_quotes.request.subheading') }}</p>

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
        @if (session('status') === 'email-verification-required')
            <div class="mb-6 border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                {{ __('auth.verify_email.action_blocked') }}
            </div>
        @endif

        @if ($currencies->isEmpty())
            <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                <p class="text-sm text-muted">{{ __('exchange_quotes.request.no_partners_for_currency') }}</p>
            </div>
        @else
            <form
                method="POST"
                action="{{ route('exchange.request.store') }}"
                class="space-y-8 rounded-2xl border border-placeholder p-6 shadow-sm sm:p-8"
                novalidate
                x-data="{
                    currency: '{{ old('currency_code', $selectedCurrency->code) }}',
                    direction: '{{ old('rate_field', 'buy_rate') }}',
                    minimums: @js($minimums),
                    directionLabels: @js($directionLabels),
                }"
            >
                @csrf

                {{-- Honeypot: hidden from real visitors, a bot filling every field trips it (see ExchangeQuoteController::store) --}}
                <div class="hidden" aria-hidden="true">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('exchange_quotes.request.section_amount') }}</p>

                    <div class="mt-4">
                        <label for="currency_code" class="block text-sm font-medium text-ink">{{ __('exchange_quotes.request.currency') }}</label>
                        <select
                            name="currency_code"
                            id="currency_code"
                            x-model="currency"
                            required
                            class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        >
                            @foreach ($currencies as $currencyOption)
                                <option value="{{ $currencyOption->code }}" @selected(old('currency_code', $selectedCurrency->code) === $currencyOption->code)>
                                    {{ $currencyOption->code }} — {{ $currencyOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-4">
                        <label for="amount" class="block text-sm font-medium text-ink">{{ __('exchange_quotes.request.amount') }}</label>
                        <input
                            type="number" step="0.01" min="0.01" required
                            name="amount" id="amount"
                            value="{{ old('amount') }}"
                            class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('amount') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                        >
                        @error('amount')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @else
                            <p
                                class="mt-1.5 text-xs text-subtle"
                                x-show="minimums[currency]"
                                x-cloak
                                x-text="'{{ __('exchange_quotes.request.minimum_prefix') }} ' + new Intl.NumberFormat('en-US').format(minimums[currency] ?? 0) + ' ' + currency"
                            ></p>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-ink">{{ __('exchange_quotes.request.direction') }}</label>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label class="group cursor-pointer">
                                <input type="radio" name="rate_field" value="buy_rate" x-model="direction" class="peer sr-only" required>
                                <span class="flex items-center justify-center rounded-xl border border-border-muted px-3 py-3 text-center text-sm font-medium text-ink transition peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 group-hover:border-primary/60" x-text="directionLabels[currency]?.buy_rate ?? ''"></span>
                            </label>
                            <label class="group cursor-pointer">
                                <input type="radio" name="rate_field" value="sell_rate" x-model="direction" class="peer sr-only">
                                <span class="flex items-center justify-center rounded-xl border border-border-muted px-3 py-3 text-center text-sm font-medium text-ink transition peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 group-hover:border-primary/60" x-text="directionLabels[currency]?.sell_rate ?? ''"></span>
                            </label>
                        </div>
                        @error('rate_field')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="border-t border-placeholder pt-6">
                    <label for="notes" class="block text-sm font-medium text-ink">{{ __('exchange_quotes.request.notes') }}</label>
                    <textarea
                        name="notes"
                        id="notes"
                        rows="3"
                        placeholder="{{ __('exchange_quotes.request.notes_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('notes') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @guest
                    <div class="border-t border-placeholder pt-6">
                        <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('exchange_quotes.request.section_details') }}</p>

                        <div class="mt-4 space-y-4">
                            <x-form-input name="guest_name" :label="__('exchange_quotes.request.your_name')" required />

                            <div>
                                <x-form-input type="email" name="guest_email" :label="__('exchange_quotes.request.your_email')" required />
                                <p class="mt-1.5 text-xs text-subtle">
                                    {{ __('exchange_quotes.request.your_email_hint') }}
                                    <a href="{{ route('exchange.resend') }}" class="text-primary hover:underline">{{ __('exchange_quotes.resend.heading') }}</a>
                                </p>
                            </div>
                        </div>
                    </div>
                @endguest

                <div class="border-t border-placeholder pt-6">
                    <label class="flex items-start gap-2 text-sm text-ink">
                        <input type="checkbox" name="consent" value="1" required class="mt-0.5 rounded border-border-muted text-primary focus:ring-primary">
                        <span>{{ __('exchange_quotes.request.consent') }}</span>
                    </label>
                    @error('consent')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="mt-6 w-full bg-primary px-6 py-3 text-sm font-medium text-white transition hover:bg-primary-dark sm:w-auto">
                        {{ __('exchange_quotes.request.submit') }}
                    </button>
                </div>
            </form>
        @endif
    </section>
@endsection
