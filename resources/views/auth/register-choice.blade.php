@extends('layouts.app')

@section('title', __('auth.choose_account_type') . ' — Findex')

@php
    $options = [
        [
            'href' => route('register.customer'),
            'title' => __('auth.register_as_customer'),
            'body' => __('auth.register_as_customer_body'),
            'color' => 'slide-green',
        ],
        [
            'href' => route('org.register'),
            'title' => __('auth.register_as_organization'),
            'body' => __('auth.register_as_organization_body'),
            'color' => 'slide-blue',
        ],
        [
            'href' => route('writer.register'),
            'title' => __('auth.register_as_writer'),
            'body' => __('auth.register_as_writer_body'),
            'color' => 'accent-yellow',
        ],
    ];
@endphp

@section('content')
    <section class="mx-auto max-w-3xl px-6 py-16 lg:py-24">
        <h1 class="text-center font-heading text-2xl font-bold text-ink">{{ __('auth.choose_account_type') }}</h1>

        <div class="mx-auto mt-10 grid grid-cols-1 gap-4 text-left sm:grid-cols-3">
            @foreach ($options as $option)
                <a
                    href="{{ $option['href'] }}"
                    class="block rounded-2xl bg-white p-6 shadow-sm ring-1 ring-placeholder/60 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-primary"
                >
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/20 font-heading text-xs font-bold"
                        style="color: var(--color-{{ $option['color'] }})"
                    >
                        {{ Str::of($option['title'])->substr(0, 1)->upper() }}
                    </span>
                    <p class="mt-3 font-heading text-sm font-semibold text-ink">{{ $option['title'] }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-muted">{{ $option['body'] }}</p>
                </a>
            @endforeach
        </div>

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('auth.have_account') }}
            <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">{{ __('auth.login_button') }}</a>
        </p>
    </section>
@endsection
