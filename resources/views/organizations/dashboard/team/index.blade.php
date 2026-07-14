@extends('layouts.dashboard')

@section('title', __('org.team.title'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.team.title') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('org.team.subtitle') }}</p>

    <div class="mt-6 divide-y divide-placeholder border-t border-placeholder">
        @foreach ($teammates as $teammate)
            <div class="flex items-center justify-between py-4 text-sm">
                <div>
                    <p class="font-medium text-ink">
                        {{ $teammate->name }}
                        @if ($teammate->is(auth('organization')->user()))
                            <span class="ml-2 text-xs text-subtle">({{ __('org.team.you_label') }})</span>
                        @endif
                    </p>
                    <p class="text-xs text-muted">{{ $teammate->email }}</p>
                </div>

                @unless ($teammate->is(auth('organization')->user()) || $teammates->count() <= 1)
                    <form method="POST" action="{{ route('org.dashboard.team.destroy', $teammate) }}" onsubmit="return confirm('{{ __('org.team.remove_confirm') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-medium text-red-600 hover:underline">{{ __('org.team.remove_button') }}</button>
                    </form>
                @endunless
            </div>
        @endforeach
    </div>

    <h2 class="mt-10 font-heading text-lg font-semibold text-ink">{{ __('org.team.add_button') }}</h2>

    <form method="POST" action="{{ route('org.dashboard.team.store') }}" class="mt-4 max-w-xl space-y-5" novalidate>
        @csrf

        <x-form-input name="name" :label="__('org.team.name')" required autofocus />
        <x-form-input name="email" type="email" :label="__('org.team.email')" required />
        <x-form-input name="password" type="password" :label="__('org.team.password')" required />
        <x-form-input name="password_confirmation" type="password" :label="__('org.team.password_confirmation')" required />

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.team.add_button') }}
        </button>
    </form>
@endsection
