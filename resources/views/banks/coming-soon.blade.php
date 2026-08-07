@extends('layouts.app')

@section('title', __('offers.categories.'.$category.'.title') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <a href="{{ route('banks.index') }}" class="text-sm font-medium text-primary hover:underline">
            &larr; {{ __('offers.back_to_all') }}
        </a>

        <div class="mt-8 rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
            <h1 class="font-heading text-xl font-semibold text-ink">{{ __('offers.categories.'.$category.'.title') }}</h1>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">{{ __('offers.coming_soon') }}</p>

            <a href="{{ route('banks.show', 'mortgages') }}" class="mt-6 inline-block bg-primary px-6 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-primary-dark">
                {{ __('offers.explore_mortgages') }}
            </a>
        </div>
    </section>
@endsection
