@extends('layouts.app')

@section('title', __('alerts.heading') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('alerts.heading') }}</h1>
        <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('alerts.subtitle') }}</p>

        @if (session('status') === 'alert-created')
            <div class="mt-8 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('alerts.status_created') }}
            </div>
        @elseif (session('status') === 'alert-deleted')
            <div class="mt-8 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm text-muted">
                {{ __('alerts.status_deleted') }}
            </div>
        @elseif (session('status') === 'telegram-disconnected')
            <div class="mt-8 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm text-muted">
                {{ __('alerts.status_telegram_disconnected') }}
            </div>
        @elseif (session('status') === 'viber-connected')
            <div class="mt-8 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('alerts.status_viber_connected') }}
            </div>
        @elseif (session('status') === 'viber-disconnected')
            <div class="mt-8 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm text-muted">
                {{ __('alerts.status_viber_disconnected') }}
            </div>
        @endif

        {{-- Existing alerts --}}
        <h2 class="mt-12 font-heading text-xl font-semibold text-ink">{{ __('alerts.existing_heading') }}</h2>

        <div class="mt-6 flex flex-col gap-3">
            @forelse ($alerts as $alert)
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-placeholder bg-white px-5 py-4 shadow-sm">
                    <div>
                        <p class="text-sm font-medium text-ink">
                            {{ $alert->currency->code }} ·
                            {{ $alert->rate_field === 'buy_rate' ? __('organizations.buy') : __('organizations.sell') }}
                            {{ __('alerts.' . $alert->direction) }}
                            {{ number_format($alert->threshold, 2) }}
                        </p>
                        <p class="mt-1 text-xs text-subtle">
                            {{ $alert->organization?->name ?? __('alerts.any_organization') }}
                            · {{ __('organizations.rate_types.' . $alert->rate_type) }}
                            · {{ __('alerts.form.channel_' . $alert->channel) }}
                            · <span class="{{ $alert->is_active ? 'text-primary' : 'text-subtle' }}">{{ $alert->is_active ? __('alerts.active') : __('alerts.paused') }}</span>
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-4">
                        <form method="POST" action="{{ route('alerts.toggle', $alert) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-xs font-medium text-primary hover:underline">
                                {{ $alert->is_active ? __('alerts.pause') : __('alerts.resume') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('alerts.destroy', $alert) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-subtle hover:text-red-600">
                                {{ __('alerts.delete') }}
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="rounded-2xl border border-dashed border-placeholder px-5 py-8 text-center text-sm text-muted">
                    {{ __('alerts.no_alerts') }}
                </p>
            @endforelse
        </div>

        {{-- Create form --}}
        <h2 id="create-alert" class="mt-12 font-heading text-xl font-semibold text-ink scroll-mt-24">{{ __('alerts.create_heading') }}</h2>

        <form
            method="POST"
            action="{{ route('alerts.store') }}"
            class="mt-6 grid grid-cols-1 gap-5 rounded-2xl border border-placeholder bg-white p-6 shadow-sm sm:grid-cols-2 sm:p-8"
            x-data="{ channel: @js(old('channel', 'email')) }"
        >
            @csrf

            {{--
                Fields default to old() first (a failed submission should
                restore exactly what was typed) and fall back to the query
                string second - lets rates-table.blade.php deep-link here
                with currency/organization/rate_type/rate_field prefilled
                instead of the user re-entering what they were just looking at.
            --}}
            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('alerts.form.currency') }}</span>
                <select name="currency_id" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected(old('currency_id', request()->query('currency_id')) == $currency->id)>{{ $currency->code }}</option>
                    @endforeach
                </select>
                @error('currency_id')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('alerts.form.organization') }}</span>
                <select name="organization_id" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                    <option value="">{{ __('alerts.any_organization') }}</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected(old('organization_id', request()->query('organization_id')) == $organization->id)>{{ $organization->name }}</option>
                    @endforeach
                </select>
                @error('organization_id')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('alerts.form.rate_type') }}</span>
                <select name="rate_type" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                    @foreach ($rateTypes as $rateType)
                        <option value="{{ $rateType->value }}" @selected(old('rate_type', request()->query('rate_type', 'cash')) === $rateType->value)>
                            {{ __('organizations.rate_types.' . $rateType->value) }}
                        </option>
                    @endforeach
                </select>
                @error('rate_type')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('alerts.form.rate_field') }}</span>
                <select name="rate_field" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                    <option value="sell_rate" @selected(old('rate_field', request()->query('rate_field', 'sell_rate')) === 'sell_rate')>{{ __('organizations.sell') }}</option>
                    <option value="buy_rate" @selected(old('rate_field', request()->query('rate_field')) === 'buy_rate')>{{ __('organizations.buy') }}</option>
                </select>
                @error('rate_field')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('alerts.form.direction') }}</span>
                <select name="direction" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                    <option value="below" @selected(old('direction', 'below') === 'below')>{{ __('alerts.below') }}</option>
                    <option value="above" @selected(old('direction') === 'above')>{{ __('alerts.above') }}</option>
                </select>
                @error('direction')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('alerts.form.threshold') }}</span>
                <input
                    type="number" step="0.0001" min="0" name="threshold" value="{{ old('threshold') }}"
                    class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none"
                >
                @error('threshold')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block sm:col-span-2">
                <span class="text-sm font-medium text-ink">{{ __('alerts.form.channel') }}</span>
                <select name="channel" x-model="channel" class="mt-1.5 block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none">
                    <option value="email">{{ __('alerts.form.channel_email') }}</option>
                    <option value="telegram">{{ __('alerts.form.channel_telegram') }}</option>
                    <option value="viber">{{ __('alerts.form.channel_viber') }}</option>
                </select>
                @error('channel')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </label>

            {{--
                No raw "chat ID" text field anymore - a numeric Telegram chat
                ID isn't something a visitor could reasonably know off the
                top of their head. Instead, this reads the account's own
                connected state (users.telegram_chat_id, linked via the
                one-tap deep link below) - the exact same connect-token
                pattern partner organizations already use, see
                PartnerReplyHandler::handleConnect().
            --}}
            <div class="sm:col-span-2" x-show="channel === 'telegram'" x-cloak>
                @if (auth()->user()->telegram_chat_id)
                    <div class="flex items-center justify-between gap-2.5 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                        <span class="flex items-center gap-2.5">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                            </svg>
                            {{ __('alerts.telegram_connect.connected') }}
                        </span>

                        {{--
                            A plain nested <form> here would be inside the
                            page's big "Create a New Alert" form - browsers
                            silently drop a nested form's opening tag (HTML
                            doesn't allow forms inside forms), which quietly
                            turns the button into a no-op. The disconnect
                            form is declared once, standalone, further down
                            the page (see #disconnect-telegram-form) and this
                            button targets it by id instead.
                        --}}
                        <button type="submit" form="disconnect-telegram-form" class="text-xs font-medium text-subtle hover:text-red-600">
                            {{ __('alerts.telegram_connect.disconnect_button') }}
                        </button>
                    </div>
                @else
                    <div class="rounded-lg border border-placeholder bg-placeholder/10 px-4 py-4">
                        <p class="text-sm text-ink">{{ __('alerts.telegram_connect.not_connected') }}</p>

                        @if ($botUsername)
                            <a
                                href="https://t.me/{{ $botUsername }}?start={{ auth()->user()->telegram_connect_token }}"
                                target="_blank"
                                rel="noopener"
                                class="mt-3 inline-flex items-center gap-2 bg-primary px-5 py-2.5 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md"
                            >
                                <img src="{{ asset('images/telegram-logo.svg') }}" alt="" class="h-4 w-4">
                                {{ __('alerts.telegram_connect.connect_button') }}
                            </a>
                            <p class="mt-2 text-xs text-subtle">{{ __('alerts.telegram_connect.hint') }}</p>
                        @endif
                    </div>
                @endif
            </div>

            {{--
                Viber has no bot-token self-service flow like Telegram's, so
                there's no deep link to open here - "Connect" is a plain
                in-app action (see RateAlertController::connectViber()).
            --}}
            <div class="sm:col-span-2" x-show="channel === 'viber'" x-cloak>
                @if (auth()->user()->viber_chat_id)
                    <div class="flex items-center justify-between gap-2.5 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                        <span class="flex items-center gap-2.5">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                            </svg>
                            {{ __('alerts.viber_connect.connected') }}
                        </span>

                        {{-- Standalone form further down the page, same
                        nested-form pitfall as the Telegram disconnect button
                        above - see #disconnect-viber-form. --}}
                        <button type="submit" form="disconnect-viber-form" class="text-xs font-medium text-subtle hover:text-red-600">
                            {{ __('alerts.viber_connect.disconnect_button') }}
                        </button>
                    </div>
                @else
                    <div class="rounded-lg border border-placeholder bg-placeholder/10 px-4 py-4">
                        <p class="text-sm text-ink">{{ __('alerts.viber_connect.not_connected') }}</p>

                        <button type="submit" form="connect-viber-form" class="mt-3 inline-flex items-center gap-2 bg-primary px-5 py-2.5 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0">
                                <path d="M10 2a7 7 0 0 0-6 10.6L3 18l5.5-1a7 7 0 1 0 1.5-15Zm3.6 10.1c-.2.5-1 1-1.5 1.1-.4.1-.9.1-1.4-.1-.3-.1-.8-.3-1.3-.6-2.3-1-3.8-3.4-3.9-3.5-.1-.2-.9-1.2-.9-2.3s.6-1.6.8-1.9c.2-.2.5-.3.6-.3h.5c.2 0 .4 0 .5.4l.7 1.7c.1.2.1.3 0 .5l-.3.4-.4.4c-.1.1-.2.3-.1.5.2.3.7 1.2 1.5 1.9.9.9 1.7 1.1 2 1.3.2.1.4.1.5-.1l.6-.7c.2-.2.4-.2.6-.1l1.5.7c.2.1.3.2.4.3.1.1.1.6-.1 1.1Z" />
                            </svg>
                            {{ __('alerts.viber_connect.connect_button') }}
                        </button>
                        <p class="mt-2 text-xs text-subtle">{{ __('alerts.viber_connect.hint') }}</p>
                    </div>
                @endif
            </div>

            <div class="sm:col-span-2">
                <button
                    type="submit"
                    class="bg-primary px-6 py-3 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md"
                >
                    {{ __('alerts.form.submit') }}
                </button>
            </div>
        </form>

        <form id="disconnect-telegram-form" method="POST" action="{{ route('alerts.telegram.disconnect') }}" class="hidden">
            @csrf
        </form>

        <form id="connect-viber-form" method="POST" action="{{ route('alerts.viber.connect') }}" class="hidden">
            @csrf
        </form>

        <form id="disconnect-viber-form" method="POST" action="{{ route('alerts.viber.disconnect') }}" class="hidden">
            @csrf
        </form>
    </section>
@endsection
