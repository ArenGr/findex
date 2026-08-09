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
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/50 p-4 sm:items-center">
        <div
            @click.outside="open = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rate-alert-title"
            class="w-full max-w-lg rounded-2xl bg-white shadow-xl"
            x-transition
        >
            <div class="flex items-start justify-between gap-4 border-b border-placeholder px-6 py-5">
                <div class="min-w-0">
                    <h2 id="rate-alert-title" class="font-heading text-xl font-bold break-words text-ink">
                        {{ __('rates.alert_cta') }}
                    </h2>
                    <p class="mt-1 text-sm break-words text-muted">{{ __('alerts.modal.subtitle') }}</p>
                </div>
                <button type="button" @click="open = false" class="shrink-0 text-2xl leading-none text-muted hover:text-ink" aria-label="{{ __('alerts.modal.cancel') }}">
                    &times;
                </button>
            </div>

            @guest
                {{-- Every alert route is behind auth, so a guest who filled this
                in would be bounced to login and lose it. Ask first instead. --}}
                <div class="px-6 py-8">
                    <p class="text-sm break-words text-ink">{{ __('alerts.modal.sign_in_required') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="rounded-full bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                            {{ __('alerts.modal.sign_in') }}
                        </a>
                        <button type="button" @click="open = false" class="rounded-full border border-border-muted px-6 py-2.5 text-sm font-semibold text-ink transition hover:bg-placeholder/30">
                            {{ __('alerts.modal.cancel') }}
                        </button>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('alerts.store') }}">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                    {{-- Buy or sell rate is derived from what the visitor was
                    already doing on the page, so the modal never asks a question
                    the page has an answer to. --}}
                    <input type="hidden" name="rate_field" :value="form.rate_field">

                    <div class="grid gap-4 px-6 py-6 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-ink">{{ __('alerts.form.currency') }}</span>
                            <select name="currency_id" x-model="form.currency_id" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->id }}">{{ $currency->code }} - {{ $currency->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-ink">{{ __('alerts.form.organization') }}</span>
                            <select name="organization_id" x-model="form.organization_id" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                                <option value="">{{ __('alerts.any_organization') }}</option>
                                @foreach ($organizations as $organization)
                                    <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-ink">{{ __('alerts.form.rate_type') }}</span>
                            <select name="rate_type" x-model="form.rate_type" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                                @foreach ($rateTypes as $rateType)
                                    <option value="{{ $rateType->value }}">{{ __('organizations.rate_types.' . $rateType->value) }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-ink">{{ __('alerts.form.direction') }}</span>
                            <select name="direction" x-model="form.direction" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                                <option value="below">{{ __('alerts.modal.direction_below') }}</option>
                                <option value="above">{{ __('alerts.modal.direction_above') }}</option>
                            </select>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-sm font-medium text-ink">{{ __('alerts.form.threshold') }}</span>
                            <input
                                type="number" step="0.0001" min="0" name="threshold" x-model="form.threshold"
                                placeholder="{{ __('alerts.modal.threshold_placeholder') }}"
                                class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none"
                            >
                            {{-- Says what the rate is now, so the number typed
                            above is a decision rather than a guess. --}}
                            <span x-show="context.rate" class="mt-1.5 block text-xs break-words text-muted">
                                {{ __('alerts.modal.current_rate') }}
                                <span x-text="context.rate"></span>
                                <span x-text="context.currency"></span>
                            </span>
                            @error('threshold')
                                <span class="mt-1.5 block text-xs break-words text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="sm:col-span-2">
                            <span class="text-sm font-medium text-ink">{{ __('alerts.modal.channel_question') }}</span>
                            <div class="mt-2 flex flex-wrap gap-3">
                                @foreach ($channels as $channel)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="channel" value="{{ $channel }}" x-model="form.channel" class="peer sr-only">
                                        <span class="block rounded-lg border border-border-muted px-5 py-2.5 text-sm font-medium text-muted transition peer-checked:border-primary peer-checked:bg-primary/8 peer-checked:text-ink">
                                            {{ __('alerts.form.channel_' . $channel) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            {{-- Telegram and Viber need a linked account, which
                            only the alerts page can set up. --}}
                            @if ($channels->count() < 3)
                                <a href="{{ route('alerts.index') }}" class="mt-2 inline-block text-xs break-words text-muted underline hover:text-ink">
                                    {{ __('alerts.modal.more_channels') }}
                                </a>
                            @endif

                            @error('channel')
                                <span class="mt-1.5 block text-xs break-words text-red-600">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3 rounded-b-2xl bg-placeholder/25 px-6 py-4">
                        <button type="button" @click="open = false" class="rounded-full border border-border-muted bg-white px-6 py-2.5 text-sm font-semibold text-ink transition hover:bg-placeholder/30">
                            {{ __('alerts.modal.cancel') }}
                        </button>
                        <button type="submit" class="rounded-full bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                            {{ __('alerts.form.submit') }}
                        </button>
                    </div>
                </form>
            @endguest
        </div>
    </div>
</div>
