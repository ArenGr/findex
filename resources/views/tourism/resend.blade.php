@extends('layouts.app')

@section('title', __('tourism.resend.heading') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-md px-6 py-16 lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ __('tourism.resend.heading') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('tourism.resend.subheading') }}</p>

        @if (session('status') === 'resend-requested')
            <div class="mt-6 border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.resend.sent') }}
            </div>
        @endif

        <form method="POST" action="{{ route('tourism.resend.send') }}" class="mt-8 space-y-5">
            @csrf

            {{-- Honeypot: hidden from real visitors, a bot filling every field trips it (see QuoteRequestController::resend) --}}
            <div class="hidden" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>

            <x-form-input
                name="email"
                type="email"
                :label="__('tourism.request.your_email')"
                :placeholder="__('tourism.resend.email_placeholder')"
                required
                autofocus
            />

            <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('tourism.resend.submit') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('tourism.resend.back_to_form') }}
            <a href="{{ route('tourism.request') }}" class="font-medium text-primary hover:underline">{{ __('tourism.request.submit') }}</a>
        </p>
    </section>
@endsection
