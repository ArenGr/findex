@extends('layouts.app')

@section('title', __('tourism.review_prompts.unsubscribed_title') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-md px-6 py-16 text-center lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ __('tourism.review_prompts.unsubscribed_heading') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('tourism.review_prompts.unsubscribed_body') }}</p>

        <a href="{{ route('tourism.request', ['locale' => app()->getLocale()]) }}" class="mt-8 inline-block bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('tourism.destination_alerts.back_to_form') }}
        </a>
    </section>
@endsection
