@extends('layouts.app')

@section('title', __('meta.help_title'))
@section('description', __('meta.help_description'))

@section('content')
    <x-vertical-hero
        icon="lightbulb"
        :eyebrow="__('footer.columns.help.title')"
        :title="__('help.heading')"
        :subtitle="__('help.intro')"
    />

    <section class="site-container py-16">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            @foreach (__('help.topics') as $topic)
                <a href="{{ route($topic['route']) }}" class="block rounded-2xl border border-border bg-surface p-6 transition hover:-translate-y-0.5 hover:border-primary/40">
                    <h2 class="font-heading text-base font-semibold text-ink">{{ $topic['title'] }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-body-text">{{ $topic['body'] }}</p>
                    <span class="mt-4 inline-block text-sm font-medium text-primary">{{ $topic['link_label'] }} &rarr;</span>
                </a>
            @endforeach
        </div>

        <div class="mt-12 rounded-2xl border border-dashed border-placeholder p-6 text-center">
            <h2 class="font-heading text-base font-semibold text-ink">{{ __('help.still_need_help_heading') }}</h2>
            <p class="mt-2 text-sm text-muted">{{ __('help.still_need_help_body') }}</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('faq') }}" class="font-medium text-primary hover:underline">{{ __('help.faq_link') }}</a>
                <a href="{{ route('contact') }}" class="font-medium text-primary hover:underline">{{ __('help.contact_link') }}</a>
            </div>
        </div>
    </section>
@endsection
