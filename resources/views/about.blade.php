@extends('layouts.app')

@section('title', __('meta.about_title'))
@section('description', __('meta.about_description'))

@section('content')
    @php
        $values = [
            ['title' => __('about.values.transparency.title'), 'body' => __('about.values.transparency.body'), 'color' => 'slide-green'],
            ['title' => __('about.values.real_time.title'), 'body' => __('about.values.real_time.body'), 'color' => 'slide-blue'],
            ['title' => __('about.values.unbiased.title'), 'body' => __('about.values.unbiased.body'), 'color' => 'accent-yellow'],
        ];

        $stats = [
            ['value' => \App\Models\Organization::query()->active()->count(), 'label' => __('about.stats.banks_label')],
            ['value' => \App\Models\Currency::where('is_active', true)->count(), 'label' => __('about.stats.currencies_label')],
            ['value' => __('about.stats.realtime_value'), 'label' => __('about.stats.realtime_label')],
        ];
    @endphp

    {{-- The About hero: what Findex is on the left, the five things it compares on the right.
         The band colour is the shared one (see .section-hero); each tile carries its own
         vertical's accent, which is the point of the row. --}}
    <section class="section-hero overflow-hidden">
        <div class="site-container grid grid-cols-1 items-center gap-12 py-14 lg:grid-cols-2 lg:py-20">
            <div>
                <span class="inline-flex rounded-full bg-primary/15 px-4 py-2 text-sm font-medium text-ink">
                    {{ __('nav.about') }}
                </span>

                <h1 class="mt-6 font-heading text-3xl leading-tight font-bold break-words text-ink sm:text-4xl">{{ __('about.hero.title') }}</h1>
                <p class="mt-4 max-w-md text-base leading-relaxed text-muted">{{ __('about.hero.subtitle') }}</p>
            </div>

            <div class="relative mx-auto w-full max-w-sm">
                {{-- Not -z-10: a negative layer would fall behind the band's own background. --}}
                <div class="absolute -inset-3 rounded-[2rem] bg-hero-purple/25 sm:-inset-6" aria-hidden="true"></div>

                <ul class="relative grid grid-cols-2 gap-4">
                    @foreach ([
                        ['label' => __('about.hero.pillars.cards'), 'color' => 'tone-banking'],
                        ['label' => __('about.hero.pillars.rates'), 'color' => 'tone-rates'],
                        ['label' => __('about.hero.pillars.mortgages'), 'color' => 'accent-yellow'],
                        ['label' => __('about.hero.pillars.insurance'), 'color' => 'tone-insurance'],
                        ['label' => __('about.hero.pillars.travel'), 'color' => 'tone-travel'],
                    ] as $i => $pillar)
                        <li @class(['rounded-2xl bg-white p-5 shadow-sm ring-1 ring-placeholder/60', 'mt-6' => $i % 2 === 1])>
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-placeholder/20">
                                <span class="h-4 w-4 rounded-full" style="background-color: var(--color-{{ $pillar['color'] }})"></span>
                            </span>
                            <p class="mt-3 text-sm font-semibold text-ink">{{ $pillar['label'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Mission --}}
    <section class="site-container py-16">
        <h2 class="font-heading text-xl font-semibold text-ink">{{ __('about.mission.title') }}</h2>
        <p class="mt-4 max-w-3xl text-sm leading-relaxed text-body-text">{{ __('about.mission.body') }}</p>
    </section>

    {{-- Values --}}
    <section class="border-t border-placeholder bg-white">
        <div class="site-container py-16">
            <h2 class="font-heading text-xl font-semibold text-ink">{{ __('about.values.title') }}</h2>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
                @foreach ($values as $value)
                    <div class="rounded-2xl border border-placeholder p-6">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-placeholder/20">
                            <span class="h-3 w-3 rounded-full" style="background-color: var(--color-{{ $value['color'] }})"></span>
                        </span>
                        <h3 class="mt-4 font-heading text-base font-semibold text-ink">{{ $value['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted">{{ $value['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Stats --}}
    <section class="border-t border-placeholder">
        <div class="site-container py-16">
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-3">
                @foreach ($stats as $stat)
                    <div>
                        <p class="font-heading text-3xl font-bold text-primary">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="border-t border-placeholder bg-white">
        <div class="site-container py-16">
            <h2 class="font-heading text-xl font-semibold text-ink">{{ __('about.how_it_works.title') }}</h2>

            <div class="mt-8 grid grid-cols-1 gap-8 sm:grid-cols-3">
                @foreach (__('about.how_it_works.steps') as $index => $step)
                    <div>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-yellow text-sm font-bold text-ink">
                            {{ $index }}
                        </span>
                        <h3 class="mt-4 font-heading text-base font-semibold text-ink">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="border-t border-placeholder bg-primary/5">
        <div class="site-container py-16 text-center">
            <h2 class="font-heading text-xl font-semibold text-ink">{{ __('about.cta.title') }}</h2>
            @auth
                @feature('rate_alerts')
                <p class="mx-auto mt-2 max-w-xl text-sm text-muted">{{ __('about.cta.subtitle_authenticated') }}</p>
                <a
                    href="{{ route('alerts.index') }}"
                    class="btn btn-primary mt-6 inline-flex"
                >
                    {{ __('about.cta.button_authenticated') }}
                </a>
                @endfeature
            @else
                <p class="mx-auto mt-2 max-w-xl text-sm text-muted">{{ __('about.cta.subtitle') }}</p>
                <a
                    href="{{ route('register') }}"
                    class="btn btn-primary mt-6 inline-flex"
                >
                    {{ __('about.cta.button') }}
                </a>
            @endauth
        </div>
    </section>
@endsection
