<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', __('meta.home_description'))">
    <title>@yield('title', __('meta.home_title'))</title>

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

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-body-text antialiased">
    <x-site-header />

    <main>
        @yield('content')
    </main>

    <x-site-footer />
</body>
</html>
