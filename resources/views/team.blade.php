@extends('layouts.app')

@section('title', __('meta.team_title'))
@section('description', __('meta.team_description'))

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('team.heading') }}</h1>

        <p class="mt-6 text-base leading-relaxed text-body-text">{{ __('team.intro') }}</p>
        <p class="mt-4 text-base leading-relaxed text-body-text">{{ __('team.body') }}</p>

        <div class="mt-10 rounded-2xl border border-placeholder p-6">
            <p class="text-sm text-muted">{{ __('team.join_prompt') }}</p>
            <a href="{{ route('careers') }}" class="mt-2 inline-block font-medium text-primary hover:underline">
                {{ __('team.join_link') }} &rarr;
            </a>
        </div>
    </section>
@endsection
