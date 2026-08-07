@extends('layouts.app')

@section('title', __('offers.heading') . ' — Findex')
@section('description', __('meta.offers_description'))

@php
    $icons = [
        'mortgages' => '🏠',
        'personal-loans' => '💰',
        'banking' => '🏦',
        'credit-cards' => '💳',
        'business-loans' => '💼',
        'investing' => '📈',
        'student-loans' => '🎓',
    ];
@endphp

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('offers.heading') }}</h1>
        <p class="mt-4 max-w-2xl text-base leading-relaxed text-muted">{{ __('offers.intro') }}</p>

        <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Browsing by individual bank (ratings, reviews, contact info)
            is a different, complementary way into the same data - not a
            product category, so it's visually distinct (filled background)
            rather than one more tile in the loop below. --}}
            <a
                href="{{ route('banks.all') }}"
                class="group block rounded-2xl border border-primary/30 bg-primary/5 p-6 shadow-sm transition hover:border-primary/60 hover:shadow-md"
            >
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-2xl">
                    🏛️
                </span>

                <h2 class="mt-4 font-heading text-base font-semibold text-ink">{{ __('offers.all_banks_title') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-body-text">{{ __('offers.all_banks_body') }}</p>

                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">
                    {{ __('offers.all_banks_link') }}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current transition-transform duration-300 group-hover:translate-x-1">
                        <path d="M5 12h14M13 6l6 6-6 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
            </a>

            @foreach ($categories as $slug => $available)
                <a
                    href="{{ route('banks.show', $slug) }}"
                    class="group block rounded-2xl border border-placeholder p-6 shadow-sm transition hover:border-primary/40 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-placeholder/20 text-2xl">
                            {{ $icons[$slug] }}
                        </span>
                        @unless ($available)
                            <span class="shrink-0 rounded-full bg-placeholder/60 px-2.5 py-1 text-[10px] font-semibold tracking-wide text-subtle uppercase">
                                {{ __('offers.soon_badge') }}
                            </span>
                        @endunless
                    </div>

                    <h2 class="mt-4 font-heading text-base font-semibold text-ink">{{ __('offers.categories.'.$slug.'.title') }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-body-text">{{ __('offers.categories.'.$slug.'.body') }}</p>

                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">
                        {{ $available ? __('offers.compare_link') : __('offers.soon_link') }}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current transition-transform duration-300 group-hover:translate-x-1">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endsection
