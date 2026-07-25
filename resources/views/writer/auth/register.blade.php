@extends('layouts.app')

@section('title', __('writer.register_title') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-md px-6 py-16 lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ __('writer.register_title') }}</h1>

        <form method="POST" action="{{ route('writer.register') }}" class="mt-8 space-y-5" novalidate>
            @csrf

            <x-form-input name="name" :label="__('writer.profile.name')" required autofocus />
            <x-form-input name="email" type="email" :label="__('auth.email')" required />
            <x-form-input name="password" type="password" :label="__('auth.password')" required />
            <x-form-input name="password_confirmation" type="password" :label="__('auth.confirm_password')" required />
            <x-form-input name="expertise" :label="__('writer.profile.expertise')" />
            <x-form-input name="topics" :label="__('writer.profile.topics')" />

            <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('writer.register_title') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('auth.have_account') }}
            <a href="{{ route('writer.login') }}" class="font-medium text-primary hover:underline">{{ __('auth.login_button') }}</a>
        </p>
    </section>
@endsection
