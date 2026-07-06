@extends('layouts.dashboard')

@section('title', __('org.profile.title'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.profile.title') }}</h1>

    <form method="POST" action="{{ route('org.dashboard.profile.update') }}" class="mt-6 max-w-xl space-y-5" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <x-form-input name="name" :label="__('org.profile.name')" :value="$organization->name" required />

        <div>
            <label for="description" class="block text-sm font-medium text-ink">{{ __('org.profile.description') }}</label>
            <textarea
                name="description"
                id="description"
                rows="4"
                class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('description') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
            >{{ old('description', $organization->description) }}</textarea>
            @error('description')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <x-form-input name="website" type="url" :label="__('org.profile.website')" :value="$organization->website" />

        <div>
            <label for="logo" class="block text-sm font-medium text-ink">{{ __('org.profile.logo') }}</label>
            @if ($organization->logo)
                <img src="{{ $organization->logo }}" alt="{{ $organization->name }}" class="mt-2 h-12 w-12 rounded-full object-contain">
            @endif
            <input
                type="file"
                name="logo"
                id="logo"
                accept="image/*"
                class="mt-1.5 block w-full text-sm text-ink"
            >
            @error('logo')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.profile.save') }}
        </button>
    </form>
@endsection
