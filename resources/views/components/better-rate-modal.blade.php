@props(['currencies', 'cities' => [], 'minimums' => []])

@php
    // A failed POST lands back with the input in the session. Reopening the
    // dialog is the difference between "fix one field" and "start again".
    $reopen = $errors->any() && old('from_modal') !== null;
@endphp

{{--
    One question - how much, and how long will you wait - asked about a rate the
    visitor is already looking at, so it belongs on top of that rather than on a
    page of its own.

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
        context: { rate: null, total: null, code: '', organization: '' },
        opener: null,
        show(detail) {
            this.form = { ...this.form, ...(detail?.form ?? {}) };
            this.context = { ...this.context, ...(detail?.context ?? {}) };
            // Remembered so closing puts the caret back where it came from,
            // rather than dropping it at the top of the document.
            this.opener = document.activeElement;
            this.open = true;
            this.$nextTick(() => (this.$refs.panel?.querySelector('#modal-amount') ?? this.$refs.panel?.querySelector('button'))?.focus());
        },
        close() {
            this.open = false;
            this.opener?.focus?.();
        },
        {{-- A dialog you can Tab out of is a dialog that hands the keyboard to
        the page it is covering, where every control is inert behind a backdrop
        the pointer cannot reach either. --}}
        trap(event) {
            const focusable = [...this.$refs.panel.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea')]
                .filter((node) => !node.disabled && node.offsetParent !== null);
            if (!focusable.length) {
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    @better-rate-open.window="show($event.detail)"
    @keydown.escape.window="open && close()"
    x-cloak
>
    {{-- A bottom sheet on a phone and a centred dialog from sm up - the same
    panel either way, so there is one of these to maintain rather than two. --}}
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-ink/50 backdrop-blur-sm sm:items-center sm:p-4">
        <div
            x-ref="panel"
            @click.outside="open && close()"
            @keydown.tab="trap($event)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="better-rate-title"
            class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-[0_-8px_30px_rgba(27,28,29,0.12)] sm:max-h-[calc(100vh-2rem)] sm:max-w-[520px] sm:rounded-2xl sm:shadow-[0_24px_40px_rgba(27,28,29,0.14)]"
            x-transition
        >
            {{-- Says the sheet can be dragged away, on the one viewport where
            that is the gesture people reach for. --}}
            <div class="flex shrink-0 justify-center pt-3 pb-1 sm:hidden" aria-hidden="true">
                <span class="h-1.5 w-12 rounded-full bg-placeholder"></span>
            </div>

            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-placeholder px-5 pt-4 pb-4 sm:px-6 sm:pt-6">
                <div class="min-w-0">
                    <h2 id="better-rate-title" class="font-heading text-xl font-bold tracking-tight break-words text-ink">
                        {{ __('rates.cta_button') }}
                    </h2>
                    {{-- Named when the request started from one office's page.
                    It still goes to every reachable office - that is what makes
                    the offers worth comparing - so the subtitle names where you
                    came from, not who will answer. --}}
                    <p class="mt-1.5 text-sm leading-relaxed break-words text-muted" x-show="context.organization" x-cloak>
                        {{ __('exchange_quotes.modal.for_organization', ['name' => '']) }}<span x-text="context.organization" class="font-medium"></span>
                    </p>
                    <p class="mt-1.5 text-sm leading-relaxed break-words text-muted" x-show="! context.organization">
                        {{ __('exchange_quotes.modal.subtitle') }}
                    </p>
                </div>
                <button
                    type="button" @click="close()"
                    class="-mt-1 -mr-1 shrink-0 rounded-full p-2 text-muted transition hover:bg-placeholder/30 hover:text-ink"
                    aria-label="{{ __('exchange_quotes.modal.cancel') }}"
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

                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-6 sm:px-6">
                    {{-- What they would get without asking, so the request has
                    something to be measured against from the first second. --}}
                    <div x-show="context.rate" class="flex items-center justify-between gap-4 rounded-xl border border-placeholder bg-placeholder/25 p-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold tracking-wider text-muted uppercase">{{ __('exchange_quotes.modal.current_rate') }}</p>
                            <p class="mt-1 flex flex-wrap items-baseline gap-x-1.5 font-heading text-2xl font-bold break-words text-primary tabular-nums">
                                <span x-text="context.rate"></span>
                                <span class="text-sm font-medium text-muted">{{ __('exchange_quotes.request.amd') }}</span>
                            </p>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-placeholder bg-white text-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                <path d="M16 7h6v6" /><path d="m22 7-8.5 8.5-5-5L2 17" />
                            </svg>
                        </span>
                    </div>

                    {{-- Everything, not just the fields that happen to have a
                    message slot beneath them. A validation failure with nowhere
                    to render is a form that rejects you and will not say why. --}}
                    @if ($errors->any())
                        <ul class="space-y-1 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
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
                        here, whichever way the trade runs, so "I want" is
                        stated rather than asked.
                    --}}
                    <div>
                        <span class="block text-[11px] font-semibold tracking-wider text-ink uppercase">{{ __('exchange_quotes.modal.exchange_details') }}</span>

                        <div class="mt-3 grid grid-cols-1 items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                            <div class="min-w-0 rounded-xl border border-border-muted p-3">
                                <label for="modal-amount" class="block text-[11px] font-semibold text-muted">{{ __('rates.i_have') }}</label>
                                <div class="mt-1 flex min-w-0 items-center gap-2">
                                    <input
                                        type="number" step="0.01" min="0.01" required
                                        name="amount" id="modal-amount" x-model="form.amount" inputmode="decimal"
                                        class="w-full min-w-0 border-0 bg-transparent p-0 text-base font-semibold text-ink tabular-nums focus:ring-0 focus:outline-none"
                                    >
                                    <label for="modal-currency" class="sr-only">{{ __('exchange_quotes.request.currency') }}</label>
                                    <select
                                        name="currency_code" id="modal-currency" x-model="form.currency_code" required
                                        class="shrink-0 border-0 bg-transparent py-0 pe-6 ps-0 text-sm font-medium text-muted focus:ring-0 focus:outline-none"
                                    >
                                        @foreach ($currencies as $currency)
                                            <option value="{{ $currency->code }}">{{ $currency->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Rotated a quarter turn on a phone, where the two
                            boxes stack instead of sitting side by side. --}}
                            <span class="flex items-center justify-center text-muted" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 rotate-90 sm:rotate-0">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </span>

                            <div class="min-w-0 rounded-xl border border-placeholder bg-placeholder/25 p-3">
                                <span class="block text-[11px] font-semibold text-muted">{{ __('rates.i_want') }}</span>
                                <p class="mt-1 text-base font-semibold break-words text-ink">{{ __('exchange_quotes.request.amd') }}</p>
                            </div>
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
                            <label for="modal-city" class="block text-[11px] font-semibold tracking-wider text-ink uppercase">{{ __('exchange_quotes.modal.where') }}</label>
                            <select
                                name="preferred_city" id="modal-city" x-model="form.preferred_city"
                                class="mt-3 min-h-11 w-full rounded-xl border border-border-muted bg-white px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                            >
                                <option value="">{{ __('exchange_quotes.modal.any_city') }}</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}">{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- The offer's shelf life. A rate held for a week is not a
                    rate anyone is holding, and an office answering a two-day-old
                    request is quoting into a market that has moved. --}}
                    <fieldset>
                        <legend class="block text-[11px] font-semibold tracking-wider text-ink uppercase">{{ __('exchange_quotes.modal.wait_question') }}</legend>
                        {{-- Three cards on a phone, one joined control from sm
                        up: the same three choices, sized for the pointer each
                        viewport actually has. --}}
                        <div class="mt-3 grid grid-cols-3 gap-2 sm:gap-0 sm:overflow-hidden sm:rounded-xl sm:border sm:border-border-muted">
                            @foreach ([['15m', '15', __('exchange_quotes.modal.wait_unit_min')], ['30m', '30', __('exchange_quotes.modal.wait_unit_min')], ['1h', '1', __('exchange_quotes.modal.wait_unit_hour')]] as [$value, $number, $unit])
                                <label class="min-w-0 cursor-pointer sm:border-e sm:border-border-muted sm:last:border-e-0">
                                    <input type="radio" name="valid_for" value="{{ $value }}" x-model="form.valid_for" class="peer sr-only">
                                    <span class="flex min-h-11 flex-col items-center justify-center rounded-xl border border-placeholder px-2 py-3 text-center text-ink transition peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 sm:h-full sm:flex-row sm:gap-1 sm:rounded-none sm:border-0">
                                        <span class="font-heading text-base font-bold tabular-nums">{{ $number }}</span>
                                        <span class="text-xs break-words opacity-70">{{ $unit }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    @guest
                        {{-- The one field a guest is asked for, and the office
                        never sees it: it is how the private offers link reaches
                        someone who is not signed in, and what /exchange/resend
                        re-sends. Signed in, the modal asks for nothing. --}}
                        <div>
                            <label for="modal-email" class="block text-[11px] font-semibold tracking-wider text-ink uppercase">{{ __('exchange_quotes.modal.email_label') }}</label>
                            <input
                                type="email" name="guest_email" id="modal-email" required
                                value="{{ old('guest_email') }}" autocomplete="email"
                                class="mt-3 min-h-11 w-full rounded-xl border border-border-muted bg-white px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                            >
                            @error('guest_email')
                                <p class="mt-1.5 text-xs break-words text-red-600">{{ $message }}</p>
                            @else
                                <p class="mt-1.5 text-xs break-words text-muted">{{ __('exchange_quotes.modal.email_hint') }}</p>
                            @enderror
                        </div>
                    @endguest

                    {{-- True as built: the fan-out job sends the amount, the
                    direction and the city, and the partner page shows the office
                    nothing else - guest_email appears nowhere on that path. --}}
                    <div class="flex items-start gap-3 rounded-xl border border-placeholder bg-white p-3">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-5 w-5 shrink-0 text-muted" aria-hidden="true">
                            <rect width="18" height="11" x="3" y="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <p class="min-w-0 text-xs leading-relaxed break-words text-muted">
                            <strong class="font-semibold text-ink">{{ __('exchange_quotes.modal.privacy_title') }}.</strong>
                            {{ __('exchange_quotes.modal.privacy_body') }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-placeholder bg-placeholder/20 px-5 py-4 pb-6 sm:flex-row sm:justify-end sm:px-6 sm:pb-4">
                    {{-- Desktop only. The sheet already has two ways out that
                    a thumb reaches first - the handle and the X - and a second
                    button under the primary one is just a bigger footer. --}}
                    <button
                        type="button" @click="close()"
                        class="hidden min-h-11 items-center justify-center rounded-xl border border-border-muted px-6 py-3 text-sm font-semibold break-words text-ink transition hover:bg-placeholder/40 sm:inline-flex"
                    >
                        {{ __('exchange_quotes.modal.cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold break-words text-white shadow-sm transition hover:bg-primary-dark"
                    >
                        {{ __('exchange_quotes.modal.submit') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
