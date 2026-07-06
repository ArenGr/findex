@extends('layouts.app')

@section('title', __('auth.choose_account_type') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-md px-6 py-16 lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ __('auth.choose_account_type') }}</h1>

        <div class="mt-8 space-y-4">
            <a href="{{ route('register.customer') }}" class="block border border-placeholder p-6 hover:border-primary">
                <p class="font-heading text-lg font-semibold text-ink">{{ __('auth.register_as_customer') }}</p>
            </a>

            <a href="{{ route('org.register') }}" class="block border border-placeholder p-6 hover:border-primary">
                <p class="font-heading text-lg font-semibold text-ink">{{ __('auth.register_as_organization') }}</p>
            </a>
        </div>

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('auth.have_account') }}
            <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">{{ __('auth.login_button') }}</a>
        </p>
    </section>
@endsection
