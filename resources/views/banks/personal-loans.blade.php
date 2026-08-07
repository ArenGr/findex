@extends('layouts.app')

@section('title', __('offers.categories.personal-loans.title') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <a href="{{ route('banks.index') }}" class="text-sm font-medium text-primary hover:underline">
            &larr; {{ __('offers.back_to_all') }}
        </a>

        <h1 class="mt-4 font-heading text-2xl font-bold text-ink lg:text-3xl">
            {{ __('offers.categories.personal-loans.title') }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('offers.categories.personal-loans.body') }}</p>

        <div class="mt-8 overflow-hidden rounded-2xl border border-placeholder">
            <x-loan-affordability-calculator />
        </div>
    </section>
@endsection
