@extends('layouts.app')

@section('title', __('meta.contact_title'))
@section('description', __('meta.contact_description'))

@section('content')
    <x-vertical-hero
        icon="mail"
        :eyebrow="__('footer.columns.help.title')"
        :title="__('contact.heading')"
        :subtitle="__('contact.intro')"
    />

    {{-- Outer section matches the home page's column. --}}
    <section class="site-container py-16">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach (__('contact.channels') as $channel)
                <div class="rounded-2xl border border-border bg-surface p-6">
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
            <a href="{{ route('org.register') }}" class="btn btn-primary mt-4 inline-flex">
                {{ __('contact.business_link') }}
            </a>
        </div>
    </section>
@endsection
