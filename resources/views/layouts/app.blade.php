<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', __('meta.home_description'))">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

    {{-- FreeSans is the body face, so it paints the instant anything renders -
         worth starting before the CSS is parsed. Only the subsets this locale
         actually needs: Latin always (digits, punctuation and brand names
         appear in every language), plus the script the page is written in.
         @fonts handles preloading for the Bunny families; FreeSans is declared
         by hand, so its preloads are too. See tools/subset-freesans.py. --}}
    @foreach (['latin', match (app()->getLocale()) { 'hy' => 'armenian', 'ru' => 'cyrillic', default => null }] as $subset)
        @if ($subset)
            <link rel="preload" as="font" type="font/woff2" crossorigin
                  href="{{ asset("fonts/subset/freesans-400-{$subset}.woff2") }}">
        @endif
    @endforeach
</head>
{{-- min-h-dvh, not min-h-screen (100vh) - iOS Safari's address bar
expands/collapses as you scroll, and 100vh is measured against the
LARGEST possible viewport (bar collapsed), so a short page can end up
taller than what's actually visible, leaving a gap under the footer
until you scroll. dvh tracks the real, current viewport instead. --}}
<body class="flex min-h-dvh flex-col bg-white font-sans text-body-text antialiased">
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

    <main class="flex-1">
        @yield('content')
    </main>

    <x-site-footer />
    <x-compare-bar />
</body>
</html>
