@extends('layouts.app')

@section('title', __('meta.news_title'))
@section('description', __('meta.news_description'))

@section('content')
    {{-- See faq.blade.php for why the outer section matches the home
    page's max-w-7xl while the actual prose stays in a narrower inner
    wrapper. --}}
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="mx-auto max-w-3xl">
            <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('company_news.heading') }}</h1>
            <p class="mt-4 text-base leading-relaxed text-muted">{{ __('company_news.intro') }}</p>

            <div class="mt-10 space-y-4">
                @foreach (__('company_news.updates') as $update)
                    <div class="rounded-2xl border border-placeholder p-6">
                        <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold tracking-wide uppercase
                            {{ $update['status'] === 'soon' ? 'bg-placeholder/60 text-subtle' : 'bg-primary/10 text-primary' }}">
                            {{ __('company_news.status.' . $update['status']) }}
                        </span>
                        <h2 class="mt-3 font-heading text-base font-semibold text-ink">{{ $update['title'] }}</h2>
                        <p class="mt-2 text-sm leading-relaxed text-body-text">{{ $update['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
