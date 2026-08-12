@extends('layouts.app')

@section('title', __('exchange_quotes.request.heading') . ' — Findex')

@php
    // Same three accents, in the same order, as the auto-insurance request
    // page. Both pages are "tell us once, we ask around" forms, so the step
    // strip is the one thing a visitor may see twice - it should look like
    // the same strip rather than a second, unrelated one.
    $steps = collect(['slide-green', 'slide-blue', 'accent-yellow'])->map(fn (string $color, int $i) => [
        'title' => __('exchange_quotes.request.step_'.($i + 1).'_title'),
        'body' => __('exchange_quotes.request.step_'.($i + 1).'_body'),
        'color' => $color,
    ]);

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
    {{--
        Heading beside the steps rather than above them: what this is and how
        it works are one thought, and stacking them centred pushed the form -
        the thing the page exists for - a full screen down.

        The steps lose their cards. Three boxes on a tinted band read as three
        offers to choose between; these are one sequence, and a number, a title
        and a line of text say that without any chrome.
    --}}
    <section class="border-b border-placeholder bg-primary/5">
        <div class="mx-auto grid max-w-6xl gap-x-12 gap-y-10 px-6 py-14 lg:grid-cols-[minmax(0,20rem)_1fr] lg:px-10">
            <div class="min-w-0">
                <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink">{{ __('exchange_quotes.request.heading') }}</h1>
                <p class="mt-4 text-base leading-relaxed break-words text-muted">{{ __('exchange_quotes.request.subheading') }}</p>
            </div>

            <ol class="grid min-w-0 grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-3">
                @foreach ($steps as $i => $step)
                    <li class="min-w-0">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/20 font-heading text-xs font-bold" style="color: var(--color-{{ $step['color'] }})">
                            {{ $i + 1 }}
                        </span>
                        <p class="mt-3 font-heading font-bold break-words text-ink">{{ $step['title'] }}</p>
                        <p class="mt-2 text-sm leading-relaxed break-words text-muted">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
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
                    flags: @js($currencyFlags),
                    // buy_rate = bank buys your currency, you get AMD; sell_rate = reverse. Only relabels the From/To preview, doesn't change what's submitted.
                    swap() {
                        this.direction = this.direction === 'buy_rate' ? 'sell_rate' : 'buy_rate';
                    },
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

                    {{-- AMD is always the other side of every quote - shown here as a From/To pair with a swap button that flips the same `direction` the radio buttons below also control. --}}
                    <div class="mt-4 flex items-center gap-3">
                        <div class="flex-1 rounded-xl border border-border-muted bg-placeholder/10 px-4 py-3">
                            <p class="text-xs text-subtle">{{ __('exchange_quotes.request.from_label') }}</p>
                            <p class="mt-0.5 flex items-center gap-1.5 text-sm font-semibold text-ink">
                                <span x-text="direction === 'buy_rate' ? flags[currency] : flags['AMD']"></span>
                                <span x-text="direction === 'buy_rate' ? currency : 'AMD'"></span>
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="swap()"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border-muted text-muted transition hover:border-primary hover:text-primary"
                            :aria-label="@js(__('exchange_quotes.request.swap_direction'))"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="2">
                                <path d="M7 10h13l-4-4M17 14H4l4 4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <div class="flex-1 rounded-xl border border-border-muted bg-placeholder/10 px-4 py-3">
                            <p class="text-xs text-subtle">{{ __('exchange_quotes.request.to_label') }}</p>
                            <p class="mt-0.5 flex items-center gap-1.5 text-sm font-semibold text-ink">
                                <span x-text="direction === 'buy_rate' ? flags['AMD'] : flags[currency]"></span>
                                <span x-text="direction === 'buy_rate' ? 'AMD' : currency"></span>
                            </p>
                        </div>
                    </div>

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
                                    {{ $currencyFlags[$currencyOption->code] ?? '' }} {{ $currencyOption->code }} — {{ $currencyOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-4">
                        <label for="amount" class="block text-sm font-medium text-ink">{{ __('exchange_quotes.request.amount') }}</label>
                        <input
                            type="number" step="0.01" min="0.01" required
                            name="amount" id="amount"
                            {{-- Prefilled when arriving from /rates with an amount already entered. --}}
                            value="{{ old('amount', request('amount')) }}"
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

                    @if ($cities->isNotEmpty())
                        <div class="mt-4">
                            <label for="preferred_city" class="block text-sm font-medium text-ink">{{ __('exchange_quotes.request.preferred_city') }}</label>
                            <select
                                name="preferred_city"
                                id="preferred_city"
                                class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('preferred_city') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                            >
                                <option value="">{{ __('exchange_quotes.request.preferred_city_all') }}</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}" @selected(old('preferred_city') === $city)>{{ $city }}</option>
                                @endforeach
                            </select>
                            @error('preferred_city')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @else
                                <p class="mt-1.5 text-xs text-subtle">{{ __('exchange_quotes.request.preferred_city_hint') }}</p>
                            @enderror
                        </div>
                    @endif
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
