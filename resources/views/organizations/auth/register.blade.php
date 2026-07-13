@extends('layouts.app')

@section('title', __('org.register_title') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-md px-6 py-16 lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ __('org.register_title') }}</h1>

        <form method="POST" action="{{ route('org.register') }}" class="mt-8 space-y-5" novalidate>
            @csrf

            <x-form-input name="name" :label="__('org.profile.name')" required autofocus />
            <x-form-input name="email" type="email" :label="__('auth.email')" required />
            <x-form-input name="password" type="password" :label="__('auth.password')" required />
            <x-form-input name="password_confirmation" type="password" :label="__('auth.confirm_password')" required />

            <div>
                <label for="type" class="block text-sm font-medium text-ink">{{ __('organizations.type') }}</label>
                <select
                    name="type"
                    id="type"
                    required
                    class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('type') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                >
                    <option value="" disabled selected>{{ __('org.select_type') }}</option>
                    @foreach (\App\Http\Controllers\Organization\Auth\RegisteredOrganizationController::TYPES as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ __('organizations.types.' . $type) }}</option>
                    @endforeach
                </select>
                @error('type')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-form-input name="website" type="url" :label="__('org.profile.website')" />

            <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('org.register_title') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('auth.have_account') }}
            <a href="{{ route('org.login') }}" class="font-medium text-primary hover:underline">{{ __('auth.login_button') }}</a>
        </p>
    </section>
@endsection
