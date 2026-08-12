@props(['currencies', 'organizations', 'rateTypes'])

@php
    $user = auth()->user();

    // Only channels the account can actually receive on. store() rejects an
    // unconnected one server-side anyway, and a modal - unlike the alerts page -
    // has nowhere to show connection state or a connect button, so offering a
    // channel here that will bounce is a dead end.
    $channels = collect(['email' => true, 'telegram' => (bool) $user?->telegram_chat_id, 'viber' => (bool) $user?->viber_chat_id])
        ->filter()
        ->keys();

    // A failed POST lands back on this page with the modal shut and the input
    // in the session. Reopening it is the difference between "fix one field"
    // and "start again".
    $reopen = $errors->any() && old('return_to') !== null;

    $fieldLabel = 'block text-xs font-semibold tracking-wider text-muted uppercase';
    $field = 'w-full rounded-lg border border-placeholder bg-white px-4 py-3 text-sm text-ink transition focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none';
@endphp

{{--
    Lives outside #rates-panel on purpose: the panel is morphed on every filter
    click, and a dialog that gets patched underneath an open form would lose
    whatever was typed into it.

    Because it sits outside, it cannot read the panel's current state directly -
    the trigger passes that in on the event, so the prefill is whatever was on
    screen at the moment of the click rather than whatever was there at page
    load.
--}}
@if (session('status') === 'alert-created')
    {{-- The alerts page confirms its own creates. Submitting from here returns
    to the rates page instead, which would otherwise reload looking untouched -
    the one thing worse than a slow form is one that gives no sign it worked. --}}
    <div
        x-data="{ shown: true }"
        x-show="shown"
        x-init="setTimeout(() => shown = false, 8000)"
        x-transition
        class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-md rounded-xl border border-primary/30 bg-white px-5 py-4 shadow-lg sm:inset-x-auto sm:right-6"
        role="status"
    >
        <div class="flex items-start gap-3">
            <p class="min-w-0 flex-1 text-sm break-words text-ink">{{ __('alerts.status_created') }}</p>
            <button type="button" @click="shown = false" class="shrink-0 text-xl leading-none text-muted hover:text-ink" aria-label="{{ __('alerts.modal.cancel') }}">&times;</button>
        </div>
        <a href="{{ route('alerts.index') }}" class="mt-1 inline-block text-xs break-words text-muted underline hover:text-ink">
            {{ __('alerts.modal.manage') }}
        </a>
    </div>
@endif

<div
    x-data="{
        open: @js($reopen),
        {{-- Seeded from old() so a rejected submission comes back filled in.
        Overwritten wholesale by show() on a fresh open. --}}
        form: @js([
            'currency_id' => (string) old('currency_id', ''),
            'organization_id' => (string) old('organization_id', ''),
            'rate_type' => (string) old('rate_type', ''),
            'rate_field' => (string) old('rate_field', 'sell_rate'),
            'direction' => (string) old('direction', 'below'),
            'threshold' => (string) old('threshold', ''),
            'channel' => (string) old('channel', $channels->first() ?? 'email'),
        ]),
        context: { currency: '', rate: null },
        show(detail) {
            this.form = { ...this.form, ...(detail?.form ?? {}) };
            this.context = { ...this.context, ...(detail?.context ?? {}) };
            this.open = true;
        },
    }"
    @rate-alert-open.window="show($event.detail)"
    @keydown.escape.window="open = false"
    x-cloak
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 p-4 backdrop-blur-sm sm:items-center">
        {{-- The panel is a column: only the middle scrolls, so the title stays
        readable and Create Alert stays reachable on a short screen. --}}
        <div
            @click.outside="open = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rate-alert-title"
            class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-[0_24px_40px_rgba(27,28,29,0.14)]"
            x-transition
        >
            <div class="flex shrink-0 items-start justify-between gap-4 px-6 pt-8 pb-6">
                <div class="min-w-0">
                    <h2 id="rate-alert-title" class="font-heading text-2xl font-bold tracking-tight break-words text-ink">
                        {{ __('rates.alert_cta') }}
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed break-words text-muted">{{ __('alerts.modal.subtitle') }}</p>
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

            @guest
                {{-- Every alert route is behind auth, so a guest who filled this
                in would be bounced to login and lose it. Ask first instead. --}}
                <div class="px-6 pb-8">
                    <p class="text-sm break-words text-ink">{{ __('alerts.modal.sign_in_required') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="rounded-full bg-gradient-to-r from-primary-dark to-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
                            {{ __('alerts.modal.sign_in') }}
                        </a>
                        <button type="button" @click="open = false" class="rounded-full bg-placeholder/40 px-6 py-2.5 text-sm font-semibold text-ink transition hover:bg-placeholder/60">
                            {{ __('alerts.modal.cancel') }}
                        </button>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('alerts.store') }}" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                    {{-- Buy or sell rate is derived from what the visitor was
                    already doing on the page, so the modal never asks a question
                    the page has an answer to. --}}
                    <input type="hidden" name="rate_field" :value="form.rate_field">

                    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-4">
                        {{-- The four things that define which rate to watch,
                        paired off: currency with where, kind with condition. --}}
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            @php
                                $selects = [
                                    ['currency_id', __('alerts.form.currency')],
                                    ['organization_id', __('alerts.form.organization')],
                                    ['rate_type', __('alerts.form.rate_type')],
                                    ['direction', __('alerts.form.direction')],
                                ];
                            @endphp

                            @foreach ($selects as [$name, $label])
                                <div class="min-w-0 space-y-1.5">
                                    <label for="alert-{{ $name }}" class="{{ $fieldLabel }}">{{ $label }}</label>
                                    {{-- The browser's own arrow is dropped for
                                    one that matches the rest of the form at
                                    every zoom level and in every engine. --}}
                                    <div class="relative">
                                        <select
                                            name="{{ $name }}" id="alert-{{ $name }}"
                                            x-model="form.{{ $name }}"
                                            class="{{ $field }} cursor-pointer appearance-none pr-11"
                                        >
                                            @switch($name)
                                                @case('currency_id')
                                                    @foreach ($currencies as $currency)
                                                        <option value="{{ $currency->id }}">{{ $currency->code }} - {{ $currency->name }}</option>
                                                    @endforeach
                                                    @break
                                                @case('organization_id')
                                                    <option value="">{{ __('alerts.any_organization') }}</option>
                                                    @foreach ($organizations as $organization)
                                                        <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                                                    @endforeach
                                                    @break
                                                @case('rate_type')
                                                    @foreach ($rateTypes as $rateType)
                                                        <option value="{{ $rateType->value }}">{{ __('organizations.rate_types.' . $rateType->value) }}</option>
                                                    @endforeach
                                                    @break
                                                @default
                                                    <option value="below">{{ __('alerts.modal.direction_below') }}</option>
                                                    <option value="above">{{ __('alerts.modal.direction_above') }}</option>
                                            @endswitch
                                        </select>
                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-muted">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                                                <path d="m6 9 6 6 6-6" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-1.5 pt-2">
                            <label for="alert-threshold" class="{{ $fieldLabel }}">{{ __('alerts.form.threshold') }}</label>
                            <div class="relative flex items-center">
                                <input
                                    type="number" step="0.0001" min="0"
                                    name="threshold" id="alert-threshold" x-model="form.threshold"
                                    placeholder="{{ __('alerts.modal.threshold_placeholder') }}"
                                    class="{{ $field }} pr-20 text-base font-medium tabular-nums"
                                >
                                {{-- Clear of right-4 so it never collides with
                                the number field's own spinner. --}}
                                <span class="pointer-events-none absolute right-12 text-sm font-medium text-muted">{{ __('exchange_quotes.request.amd') }}</span>
                            </div>

                            {{-- Says what the rate is now, so the number typed
                            above is a decision rather than a guess. --}}
                            <p x-show="context.rate" class="flex items-center gap-1.5 pt-1 text-xs break-words text-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-3.5 w-3.5 shrink-0 fill-accent-yellow" aria-hidden="true">
                                    <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                                </svg>
                                <span class="min-w-0">
                                    {{ __('alerts.modal.current_rate') }}
                                    <span class="font-medium text-ink tabular-nums" x-text="context.rate"></span>
                                    <span x-text="context.currency"></span>
                                </span>
                            </p>

                            @error('threshold')
                                <p class="text-xs break-words text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-3 border-t border-placeholder pt-4">
                            <h3 class="font-heading text-base font-semibold break-words text-ink">{{ __('alerts.modal.channel_question') }}</h3>

                            <div class="flex flex-wrap gap-3">
                                @foreach ($channels as $channel)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="channel" value="{{ $channel }}" x-model="form.channel" class="peer sr-only">
                                        <span class="block rounded-full border border-placeholder px-5 py-2.5 text-sm font-medium text-muted transition hover:bg-placeholder/25 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white">
                                            {{ __('alerts.form.channel_' . $channel) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            {{-- Telegram and Viber need a linked account, which
                            only the alerts page can set up. --}}
                            @if ($channels->count() < 3)
                                <a href="{{ route('alerts.index') }}" class="inline-block text-sm break-words text-primary underline decoration-primary/30 underline-offset-4 transition hover:text-primary-dark">
                                    {{ __('alerts.modal.more_channels') }}
                                </a>
                            @endif

                            @error('channel')
                                <p class="text-xs break-words text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Reversed below sm so the primary action is the one under
                    the thumb rather than the one Cancel is. --}}
                    <div class="flex shrink-0 flex-col-reverse items-center justify-end gap-3 bg-placeholder/25 px-6 py-5 sm:flex-row">
                        <button type="button" @click="open = false" class="w-full rounded-full bg-placeholder/50 px-6 py-2.5 text-sm font-semibold break-words text-ink transition hover:bg-placeholder sm:w-auto">
                            {{ __('alerts.modal.cancel') }}
                        </button>
                        <button type="submit" class="w-full rounded-full bg-gradient-to-r from-primary-dark to-primary px-6 py-2.5 text-sm font-semibold break-words text-white shadow-sm transition hover:opacity-90 sm:w-auto">
                            {{ __('alerts.form.submit') }}
                        </button>
                    </div>
                </form>
            @endguest
        </div>
    </div>
</div>
