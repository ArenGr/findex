<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('writer.nav.overview')) — Findex</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-body-text antialiased">
    @php $writer = auth('writer')->user()->writer; @endphp

    <header class="border-b border-placeholder">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-5 lg:px-10">
            <a href="{{ route('writer.dashboard.index') }}" class="shrink-0 font-logo text-2xl text-primary">
                Findex
            </a>

            <div class="flex items-center gap-4 text-sm text-ink">
                <span class="text-muted">{{ $writer->name }}</span>
                <form method="POST" action="{{ route('writer.logout') }}">
                    @csrf
                    <button type="submit" class="font-medium text-primary hover:underline">{{ __('common.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-10 md:flex-row md:gap-10 lg:px-10">
        <nav class="flex gap-1 overflow-x-auto text-sm md:w-48 md:shrink-0 md:flex-col md:space-y-1 md:overflow-visible">
            @foreach ([
                'writer.dashboard.index' => __('writer.nav.overview'),
                'writer.dashboard.articles.index' => __('writer.nav.articles'),
            ] as $routeName => $label)
                <a
                    href="{{ route($routeName) }}"
                    class="block shrink-0 px-3 py-2 whitespace-nowrap md:shrink {{ request()->routeIs($routeName) || request()->routeIs(str_replace('.index', '.*', $routeName)) ? 'bg-primary/5 font-medium text-primary' : 'text-body-text hover:bg-placeholder/40' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <main class="min-w-0 flex-1">
            @unless ($writer->is_active)
                <div class="mb-6 border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                    {{ __('writer.pending_approval') }}
                </div>
            @endunless

            @unless (auth('writer')->user()->hasVerifiedEmail())
                <div class="mb-6 border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                    @if (session('status') === 'verification-link-sent')
                        {{ __('auth.verify_email.link_sent') }}
                    @else
                        {{ __('auth.verify_email.banner') }}
                        <form method="POST" action="{{ route('writer.verification.send') }}" class="inline">
                            @csrf
                            <button type="submit" class="font-medium text-primary hover:underline">{{ __('auth.verify_email.resend_button') }}</button>
                        </form>
                    @endif
                </div>
            @endunless

            @php
                $statusMessages = [
                    'article-created' => __('writer.articles.created'),
                    'article-updated' => __('writer.articles.updated'),
                    'article-deleted' => __('writer.articles.deleted'),
                    'article-submitted' => __('writer.articles.submitted'),
                    'email-verified' => __('auth.verify_email.verified_confirmation'),
                ];
            @endphp

            @if (session('status') && isset($statusMessages[session('status')]))
                <div class="mb-6 border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                    {{ $statusMessages[session('status')] }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
