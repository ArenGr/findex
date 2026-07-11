@extends('layouts.app')

@section('title', __('meta.contact_title'))
@section('description', __('meta.contact_description'))

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('contact.heading') }}</h1>
        <p class="mt-4 text-base leading-relaxed text-muted">{{ __('contact.intro') }}</p>

        <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach (__('contact.channels') as $channel)
                <div class="rounded-2xl border border-placeholder p-6">
                    <h2 class="font-heading text-sm font-semibold text-ink">{{ $channel['title'] }}</h2>
                    <p class="mt-2 text-xs leading-relaxed text-muted">{{ $channel['body'] }}</p>
                    <a href="mailto:{{ $channel['email'] }}" class="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                        {{ $channel['email'] }}
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-8 rounded-2xl bg-primary/5 p-6">
            <h2 class="font-heading text-base font-semibold text-ink">{{ __('contact.business_heading') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-body-text">{{ __('contact.business_body') }}</p>
            <a href="{{ route('org.register') }}" class="mt-4 inline-block bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('contact.business_link') }}
            </a>
        </div>
    </section>
@endsection
