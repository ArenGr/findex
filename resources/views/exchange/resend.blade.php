@extends('layouts.app')

@section('title', __('exchange_quotes.resend.heading') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-md px-6 py-16 lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ __('exchange_quotes.resend.heading') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('exchange_quotes.resend.subheading') }}</p>

        @if (session('status') === 'resend-requested')
            <div class="mt-6 border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('exchange_quotes.resend.sent') }}
            </div>
        @endif

        <form method="POST" action="{{ route('exchange.resend.send') }}" class="mt-8 space-y-5" novalidate>
            @csrf

            {{-- Honeypot: hidden from real visitors, a bot filling every field trips it (see ExchangeQuoteController::resend) --}}
            <div class="hidden" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>

            <x-form-input
                name="email"
                type="email"
                :label="__('tourism.request.your_email')"
                :placeholder="__('exchange_quotes.resend.email_placeholder')"
                required
                autofocus
            />

            <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('exchange_quotes.resend.submit') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('exchange_quotes.resend.back_to_form') }}
            <a href="{{ route('exchange.request') }}" class="font-medium text-primary hover:underline">{{ __('exchange_quotes.request.submit') }}</a>
        </p>
    </section>
@endsection
