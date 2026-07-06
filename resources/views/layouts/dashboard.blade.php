<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('org.nav.overview')) — Findex</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-body-text antialiased">
    @php $organization = auth('organization')->user(); @endphp

    <header class="border-b border-placeholder">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-5 lg:px-10">
            <a href="{{ route('org.dashboard.index') }}" class="shrink-0 font-logo text-2xl text-primary">
                Findex
            </a>

            <div class="flex items-center gap-4 text-sm text-ink">
                <span class="text-muted">{{ $organization->name }}</span>
                <form method="POST" action="{{ route('org.logout') }}">
                    @csrf
                    <button type="submit" class="font-medium text-primary hover:underline">{{ __('common.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-6xl gap-10 px-6 py-10 lg:px-10">
        <nav class="w-48 shrink-0 space-y-1 text-sm">
            @foreach ([
                'org.dashboard.index' => __('org.nav.overview'),
                'org.dashboard.profile.edit' => __('org.nav.profile'),
                'org.dashboard.reviews.index' => __('org.nav.reviews'),
                'org.dashboard.branches.index' => __('org.nav.branches'),
                'org.dashboard.rates.index' => __('org.nav.rates'),
                'org.dashboard.reports.index' => __('org.nav.reports'),
            ] as $routeName => $label)
                <a
                    href="{{ route($routeName) }}"
                    class="block px-3 py-2 {{ request()->routeIs($routeName) || request()->routeIs(str_replace('.index', '.*', $routeName)) ? 'bg-primary/5 font-medium text-primary' : 'text-body-text hover:bg-placeholder/40' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <main class="min-w-0 flex-1">
            @unless ($organization->is_active)
                <div class="mb-6 border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                    {{ __('org.pending_approval') }}
                </div>
            @endunless

            @php
                $statusMessages = [
                    'profile-updated' => __('org.profile.updated'),
                    'reply-submitted' => __('org.reviews.reply_submitted'),
                    'branch-created' => __('org.branches.created'),
                    'branch-updated' => __('org.branches.updated'),
                    'branch-deleted' => __('org.branches.deleted'),
                    'report-requested' => __('org.reports.requested'),
                    'rate-saved' => __('org.rates.saved'),
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
