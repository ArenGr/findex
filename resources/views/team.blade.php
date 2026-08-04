@extends('layouts.app')

@section('title', __('meta.team_title'))
@section('description', __('meta.team_description'))

@section('content')
    {{-- See faq.blade.php for why the outer section matches the home
    page's max-w-7xl while the actual prose stays in a narrower inner
    wrapper. --}}
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="mx-auto max-w-3xl">
            <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('team.heading') }}</h1>

            <p class="mt-6 text-base leading-relaxed text-body-text">{{ __('team.intro') }}</p>
            <p class="mt-4 text-base leading-relaxed text-body-text">{{ __('team.body') }}</p>

            <div class="mt-10 rounded-2xl border border-placeholder p-6">
                <p class="text-sm text-muted">{{ __('team.join_prompt') }}</p>
                <a href="{{ route('careers') }}" class="mt-2 inline-block font-medium text-primary hover:underline">
                    {{ __('team.join_link') }} &rarr;
                </a>
            </div>
        </div>
    </section>
@endsection
