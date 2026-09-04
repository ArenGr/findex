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
    {{-- Both weights, not just 400. FreeSans is font-display: optional, which
         decides at first paint whether to use the face at all and never
         revisits it - so a weight that is not preloaded is simply not used,
         and every bold figure and label on the page renders in the fallback
         instead. Regular text was preloaded and looked right; bold was not and
         did not.

         Weight 700 has no Armenian subset (see tools/subset-freesans.py), so
         the file is checked before it is advertised - preloading a 404 costs a
         request and warns in the console. --}}
    {{-- Montserrat, the heading face. Hashed by the build, so the filenames
         come from the font manifest - see App\Support\FontPreloads. Without
         this the browser only finds it when it reaches the first heading,
         which is far too late to make the first frame: every h1 and h2 painted
         in the fallback and then visibly re-rendered a moment later. --}}
    @foreach (App\Support\FontPreloads::urls('montserrat', app()->getLocale()) as $href)
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ $href }}">
    @endforeach

    @foreach ([400, 700] as $weight)
        @foreach (['latin', match (app()->getLocale()) { 'hy' => 'armenian', 'ru' => 'cyrillic', default => null }] as $subset)
            @if ($subset && file_exists(public_path("fonts/subset/freesans-{$weight}-{$subset}.woff2")))
                <link rel="preload" as="font" type="font/woff2" crossorigin
                      href="{{ asset("fonts/subset/freesans-{$weight}-{$subset}.woff2") }}">
            @endif
        @endforeach
    @endforeach
    {{-- Pages that use a face the rest of the site does not - the travel
         request flow and its Manrope - push their own preloads here rather
         than taxing every other page with them. --}}
    @stack('head')
</head>
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
