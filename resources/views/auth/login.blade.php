@extends('layouts.app')

@section('title', __('auth.login_title') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-md px-6 py-16 lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ __('auth.login_title') }}</h1>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf

            <x-form-input name="email" type="email" :label="__('auth.email')" required autofocus />
            <x-form-input name="password" type="password" :label="__('auth.password')" required />

            <label class="flex items-center gap-2 text-sm text-body-text">
                <input type="checkbox" name="remember" class="rounded border-border-muted text-primary focus:ring-primary">
                {{ __('auth.remember_me') }}
            </label>

            <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('auth.login_button') }}
            </button>
        </form>

        <x-google-auth-button />

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('auth.no_account') }}
            <a href="{{ route('register') }}" class="font-medium text-primary hover:underline">{{ __('auth.register_button') }}</a>
        </p>
    </section>
@endsection
