@extends('layouts.app')

@section('title', __('auth.register_title') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-md px-6 py-16 lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ __('auth.register_title') }}</h1>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
            @csrf

            <x-form-input name="name" :label="__('auth.name')" required autofocus />
            <x-form-input name="email" type="email" :label="__('auth.email')" required />
            <x-form-input name="password" type="password" :label="__('auth.password')" required />
            <x-form-input name="password_confirmation" type="password" :label="__('auth.confirm_password')" required />

            <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('auth.register_button') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('auth.have_account') }}
            <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">{{ __('auth.login_button') }}</a>
        </p>
    </section>
@endsection
