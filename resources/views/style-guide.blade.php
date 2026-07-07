@extends('layouts.app')

@section('title', __('meta.style_guide_title'))

@section('content')
    @php
        $brandColors = [
            ['name' => __('style_guide.colors.primary'), 'hex' => '#607E34', 'class' => 'bg-primary'],
            ['name' => __('style_guide.colors.accent_yellow'), 'hex' => '#F1CB40', 'class' => 'bg-accent-yellow'],
            ['name' => __('style_guide.colors.accent_blue'), 'hex' => '#005FB9', 'class' => 'bg-accent-blue'],
            ['name' => __('style_guide.colors.white'), 'hex' => '#FFFFFF', 'class' => 'bg-white border border-border-muted'],
        ];

        $slideColors = [
            ['name' => __('style_guide.colors.slide_green'), 'hex' => '#607E34', 'class' => 'bg-slide-green'],
            ['name' => __('style_guide.colors.slide_blue'), 'hex' => '#87B8F5', 'class' => 'bg-slide-blue'],
            ['name' => __('style_guide.colors.slide_yellow'), 'hex' => '#F8D97E', 'class' => 'bg-slide-yellow'],
            ['name' => __('style_guide.colors.slide_pink'), 'hex' => '#FEB2B9', 'class' => 'bg-slide-pink'],
            ['name' => __('style_guide.colors.slide_purple'), 'hex' => '#DCB0E9', 'class' => 'bg-slide-purple'],
        ];

        $neutrals = [
            ['name' => __('style_guide.colors.ink'), 'hex' => '#161515', 'class' => 'bg-ink'],
            ['name' => __('style_guide.colors.body_text'), 'hex' => '#262626', 'class' => 'bg-body-text'],
            ['name' => __('style_guide.colors.muted'), 'hex' => '#676767', 'class' => 'bg-muted'],
            ['name' => __('style_guide.colors.subtle'), 'hex' => '#A6A6A6', 'class' => 'bg-subtle'],
            ['name' => __('style_guide.colors.border_muted'), 'hex' => '#B3B3B3', 'class' => 'bg-border-muted'],
            ['name' => __('style_guide.colors.placeholder'), 'hex' => '#D9D9D9', 'class' => 'bg-placeholder'],
        ];
    @endphp

    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-3xl font-bold text-ink">{{ __('style_guide.title') }}</h1>
        <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('style_guide.subtitle') }}</p>

        {{-- Colors --}}
        <h2 class="mt-12 font-heading text-xl font-semibold text-ink">{{ __('style_guide.brand_colors') }}</h2>
        <div class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-4">
            @foreach ($brandColors as $color)
                <div>
                    <div class="h-24 rounded-lg {{ $color['class'] }}"></div>
                    <p class="mt-3 text-sm font-medium text-ink">{{ $color['name'] }}</p>
                    <p class="text-xs text-muted">{{ $color['hex'] }}</p>
                </div>
            @endforeach
        </div>

        <h2 class="mt-12 font-heading text-xl font-semibold text-ink">{{ __('style_guide.carousel_colors') }}</h2>
        <div class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-5">
            @foreach ($slideColors as $color)
                <div>
                    <div class="h-24 rounded-lg {{ $color['class'] }}"></div>
                    <p class="mt-3 text-sm font-medium text-ink">{{ $color['name'] }}</p>
                    <p class="text-xs text-muted">{{ $color['hex'] }}</p>
                </div>
            @endforeach
        </div>

        <h2 class="mt-12 font-heading text-xl font-semibold text-ink">{{ __('style_guide.neutral_colors') }}</h2>
        <div class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-6">
            @foreach ($neutrals as $color)
                <div>
                    <div class="h-24 rounded-lg {{ $color['class'] }}"></div>
                    <p class="mt-3 text-sm font-medium text-ink">{{ $color['name'] }}</p>
                    <p class="text-xs text-muted">{{ $color['hex'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Typography --}}
        <h2 class="mt-16 font-heading text-xl font-semibold text-ink">{{ __('style_guide.typography') }}</h2>

        <div class="mt-6 space-y-10">
            <div>
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('style_guide.fonts.free_sans') }}</p>
                <p class="mt-2 font-sans text-3xl text-ink">{!! __('hero.slides.1.heading') !!}</p>
                <p class="mt-1 font-sans text-3xl text-ink">The quick brown fox jumps over the lazy dog</p>
            </div>

            <div>
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('style_guide.fonts.allerta_stencil') }}</p>
                <p class="mt-2 font-logo text-3xl text-primary">Findex</p>
            </div>

            <div>
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('style_guide.fonts.montserrat') }}</p>
                <p class="mt-2 font-heading text-3xl font-bold text-ink">{!! __('hero.slides.1.heading') !!}</p>
            </div>
        </div>

        {{-- Buttons --}}
        <h2 class="mt-16 font-heading text-xl font-semibold text-ink">{{ __('style_guide.buttons') }}</h2>
        <div class="mt-6 flex flex-wrap items-center gap-4">
            <button type="button" class="bg-primary px-6 py-3 text-sm text-white hover:bg-primary-dark">{{ __('common.learn_more') }}</button>
            <button type="button" class="border border-ink px-6 py-3 text-sm text-ink hover:bg-ink hover:text-white">{{ __('common.compare_banks') }}</button>
        </div>
    </section>
@endsection
