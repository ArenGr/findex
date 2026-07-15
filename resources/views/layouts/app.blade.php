<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', __('meta.home_description'))">
    <title>@yield('title', __('meta.home_title'))</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @if (Route::current())
        @php $routeParams = Route::current()->parameters(); @endphp
        @foreach (config('localization.available') as $code => $locale)
            <link
                rel="alternate"
                hreflang="{{ $code }}"
                href="{{ route(Route::currentRouteName(), array_merge($routeParams, ['locale' => $code])) }}"
            >
        @endforeach
        <link
            rel="alternate"
            hreflang="x-default"
            href="{{ route(Route::currentRouteName(), array_merge($routeParams, ['locale' => config('localization.default')])) }}"
        >
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @fonts
</head>
<body class="min-h-screen bg-white font-sans text-body-text antialiased">
    <x-site-header />

    @if (session('status') === 'email-verified')
        <div class="border-b border-primary/30 bg-primary/5 px-6 py-3 text-center text-sm text-primary">
            {{ __('auth.verify_email.verified_confirmation') }}
        </div>
    @endif

    @auth
        @unless (auth()->user()->hasVerifiedEmail())
            <div class="border-b border-accent-yellow/40 bg-accent-yellow/10 px-6 py-3 text-center text-sm text-ink">
                @if (session('status') === 'verification-link-sent')
                    {{ __('auth.verify_email.link_sent') }}
                @else
                    {{ __('auth.verify_email.banner') }}
                    <form method="POST" action="{{ route('verification.send') }}" class="inline">
                        @csrf
                        <button type="submit" class="font-medium text-primary hover:underline">{{ __('auth.verify_email.resend_button') }}</button>
                    </form>
                @endif
            </div>
        @endunless
    @endauth

    <main>
        @yield('content')
    </main>

    <x-site-footer />
    <x-compare-bar />
</body>
</html>
