@props(['currencies', 'cities' => [], 'minimums' => []])

@php
    // A failed POST lands back with the input in the session. Reopening the
    // dialog is the difference between "fix one field" and "start again".
    $reopen = $errors->any() && old('from_modal') !== null;
@endphp

{{--
    "Get a better rate" used to be a link to a whole page. It is one question -
    how much, and how long will you wait - asked about rates the visitor is
    already looking at, so it belongs on top of them rather than somewhere else.

    Lives outside #rates-panel: that panel is morphed on every filter click, and
    a dialog patched underneath an open form would lose whatever was typed.
--}}
<div
    x-data="{
        open: @js($reopen),
        form: @js([
            'currency_code' => (string) old('currency_code', ''),
            'amount' => (string) old('amount', ''),
            'rate_field' => (string) old('rate_field', 'buy_rate'),
            'preferred_city' => (string) old('preferred_city', ''),
            'valid_for' => (string) old('valid_for', '1h'),
        ]),
        context: { rate: null, total: null, code: '' },
        show(detail) {
            this.form = { ...this.form, ...(detail?.form ?? {}) };
            this.context = { ...this.context, ...(detail?.context ?? {}) };
            this.open = true;
        },
    }"
    @better-rate-open.window="show($event.detail)"
    @keydown.escape.window="open = false"
    x-cloak
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 p-4 backdrop-blur-sm sm:items-center">
        <div
            @click.outside="open = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="better-rate-title"
            class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-[0_24px_40px_rgba(27,28,29,0.14)]"
            x-transition
        >
            <div class="flex shrink-0 items-start justify-between gap-4 px-6 pt-7 pb-4 sm:px-8">
                <div class="min-w-0">
                    <h2 id="better-rate-title" class="font-heading text-2xl font-bold tracking-tight break-words text-ink">
                        {{ __('rates.cta_button') }}
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed break-words text-muted">{{ __('exchange_quotes.modal.subtitle') }}</p>
                </div>
                <button
                    type="button" @click="open = false"
                    class="-mt-1 -mr-1 shrink-0 rounded-full p-1 text-muted transition hover:bg-placeholder/30 hover:text-ink"
                    aria-label="{{ __('alerts.modal.cancel') }}"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('exchange.request.store') }}" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <input type="hidden" name="from_modal" value="1">

                {{-- Honeypot, same as the full page: hidden from real visitors,
                a bot filling every field trips it. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="modal-company">Company</label>
                    <input type="text" name="company" id="modal-company" tabindex="-1" autocomplete="off">
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 pb-6 sm:px-8">
                    {{-- What they would get without asking, so the request has
                    something to be measured against from the first second. --}}
                    <div x-show="context.rate" class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 rounded-lg border border-primary/30 bg-primary/5 p-4">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold tracking-widest text-muted uppercase">{{ __('exchange_quotes.modal.current_rate') }}</p>
                            <p class="mt-0.5 font-heading text-lg font-bold break-words text-ink tabular-nums">
                                1 <span x-text="context.code"></span> = <span x-text="context.rate"></span> {{ __('exchange_quotes.request.amd') }}
                            </p>
                        </div>
                        <div class="min-w-0 text-end" x-show="context.total">
                            <p class="text-[10px] font-bold tracking-widest text-muted uppercase">{{ __('exchange_quotes.modal.estimated_total') }}</p>
                            <p class="mt-0.5 font-heading text-lg font-bold break-words text-ink tabular-nums">
                                <span x-text="context.total"></span> {{ __('exchange_quotes.request.amd') }}
                            </p>
                        </div>
                    </div>

                    {{-- Everything, not just the fields that happen to have a
                    message slot beneath them. A validation failure with nowhere
                    to render is a form that rejects you and will not say why -
                    which is exactly what this did for rate_field and for the
                    "nobody can quote this currency" pre-check. --}}
                    @if ($errors->any())
                        <ul class="space-y-1 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                            @foreach ($errors->all() as $message)
                                <li class="text-xs break-words text-red-700">{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif

                    {{--
                        "I have 5,000 USD" and "I want AMD". The select is the
                        currency being handed over, not the one being asked for -
                        putting it under "I want" made the modal offer to swap
                        USD for USD.

                        The amount is always denominated in the foreign currency
                        here, whichever way the trade runs: the form's minimum is
                        "1,000 USD" either way. So "I want" is stated, not asked.
                    --}}
                    <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                        <div class="min-w-0">
                            <label for="modal-amount" class="block text-xs font-semibold text-muted">{{ __('rates.i_have') }}</label>
                            <div class="mt-1.5 flex min-w-0 gap-2">
                                <input
                                    type="number" step="0.01" min="0.01" required
                                    name="amount" id="modal-amount" x-model="form.amount"
                                    class="w-full min-w-0 rounded-lg border border-border-muted bg-white px-4 py-3 text-lg font-semibold text-ink focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                                >
                                <label for="modal-currency" class="sr-only">{{ __('exchange_quotes.request.currency') }}</label>
                                <select
                                    name="currency_code" id="modal-currency" x-model="form.currency_code" required
                                    class="shrink-0 rounded-lg border border-border-muted bg-white px-3 py-3 text-lg font-semibold text-ink focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                                >
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency->code }}">{{ $currency->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="min-w-0">
                            <span class="block text-xs font-semibold text-muted">{{ __('rates.i_want') }}</span>
                            <p class="mt-1.5 rounded-lg border border-placeholder bg-placeholder/25 px-4 py-3 text-lg font-semibold break-words text-ink">
                                {{ __('exchange_quotes.request.amd') }}
                            </p>
                        </div>
                    </div>

                    {{-- Carried from wherever they pressed the button, so the
                    direction they were already looking at survives. --}}
                    <input type="hidden" name="rate_field" :value="form.rate_field">

                    {{-- Kept even though the design drops it: it decides which
                    offices are contacted at all, and without it every request
                    goes to every office in the country. --}}
                    @if ($cities !== [])
                        <div>
                            <label for="modal-city" class="block text-xs font-semibold text-muted">{{ __('exchange_quotes.modal.where') }}</label>
                            <select
                                name="preferred_city" id="modal-city" x-model="form.preferred_city"
                                class="mt-1.5 w-full rounded-lg border border-border-muted bg-white px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                            >
                                <option value="">{{ __('exchange_quotes.modal.any_city') }}</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}">{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- New: the offer's shelf life. A rate held for a week is
                    not a rate anyone is holding, and an office answering a
                    two-day-old request is quoting into a market that moved. --}}
                    <div>
                        <span class="block text-xs font-semibold break-words text-muted">{{ __('exchange_quotes.modal.wait_question') }}</span>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (['15m' => 'wait_15', '30m' => 'wait_30', '1h' => 'wait_60', 'today' => 'wait_today'] as $value => $key)
                                <label class="min-w-0 flex-1 cursor-pointer">
                                    <input type="radio" name="valid_for" value="{{ $value }}" x-model="form.valid_for" class="peer sr-only">
                                    <span class="block min-h-11 rounded-lg border border-placeholder bg-white px-2 py-2.5 text-center text-sm font-medium break-words text-ink transition peer-checked:border-primary peer-checked:bg-primary/15 peer-checked:font-bold">
                                        {{ __('exchange_quotes.modal.'.$key) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- True as built: the fan-out job sends the amount, the
                    direction and the city, and the partner page shows the office
                    nothing else. --}}
                    <div class="flex items-start gap-3 rounded-lg border border-placeholder bg-placeholder/20 p-4">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true">
                            <rect width="18" height="11" x="3" y="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold break-words text-ink">{{ __('exchange_quotes.modal.privacy_title') }}</p>
                            <p class="mt-0.5 text-[11px] leading-relaxed break-words text-muted">{{ __('exchange_quotes.modal.privacy_body') }}</p>
                        </div>
                    </div>

                    @guest
                        {{-- The full page asks a guest for these, and dropping
                        them here would quietly put a working public feature
                        behind a login. --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="modal-name" class="block text-xs font-semibold text-muted">{{ __('tourism.request.your_name') }}</label>
                                <input type="text" name="guest_name" id="modal-name" value="{{ old('guest_name') }}" required
                                       class="mt-1.5 w-full min-w-0 rounded-lg border border-border-muted bg-white px-4 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                                @error('guest_name')<p class="mt-1 text-xs break-words text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="min-w-0">
                                <label for="modal-email" class="block text-xs font-semibold text-muted">{{ __('tourism.request.your_email') }}</label>
                                <input type="email" name="guest_email" id="modal-email" value="{{ old('guest_email') }}" required
                                       class="mt-1.5 w-full min-w-0 rounded-lg border border-border-muted bg-white px-4 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                                @error('guest_email')<p class="mt-1 text-xs break-words text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endguest

                    <label class="flex items-start gap-2 text-sm break-words text-ink">
                        <input type="checkbox" name="consent" value="1" required class="mt-0.5 h-5 w-5 shrink-0 rounded border-border-muted text-primary focus:ring-primary">
                        <span class="min-w-0">{{ __('exchange_quotes.request.consent') }}</span>
                    </label>
                    @error('consent')<p class="text-xs break-words text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="shrink-0 border-t border-placeholder bg-placeholder/25 px-6 py-5 sm:px-8">
                    <button
                        type="submit"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 text-sm font-bold break-words text-white shadow-sm transition hover:bg-primary-dark"
                    >
                        {{ __('exchange_quotes.modal.submit') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M16 7h6v6" /><path d="m22 7-8.5 8.5-5-5L2 17" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
