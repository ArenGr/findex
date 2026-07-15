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
    @php $organization = auth('organization')->user()->organization; @endphp

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

    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-10 md:flex-row md:gap-10 lg:px-10">
        <nav class="flex gap-1 overflow-x-auto text-sm md:w-48 md:shrink-0 md:flex-col md:space-y-1 md:overflow-visible">
            @foreach ([
                'org.dashboard.index' => __('org.nav.overview'),
                'org.dashboard.profile.edit' => __('org.nav.profile'),
                'org.dashboard.reviews.index' => __('org.nav.reviews'),
                'org.dashboard.branches.index' => __('org.nav.branches'),
                ...($organization->hasRatesPage() ? ['org.dashboard.rates.index' => __('org.nav.rates')] : []),
                'org.dashboard.reports.index' => __('org.nav.reports'),
                'org.dashboard.team.index' => __('org.nav.team'),
                ...($organization->hasTourismPage() ? [
                    'org.dashboard.tourism.index' => __('tourism.nav_label'),
                    'org.dashboard.quote-templates.index' => __('org.nav.quote_templates'),
                ] : []),
                ...($organization->hasInsurancePage() ? ['org.dashboard.insurance.index' => __('org.nav.insurance')] : []),
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
            @unless ($organization->is_active)
                <div class="mb-6 border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                    {{ __('org.pending_approval') }}
                </div>
            @endunless

            @unless (auth('organization')->user()->hasVerifiedEmail())
                <div class="mb-6 border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                    @if (session('status') === 'verification-link-sent')
                        {{ __('auth.verify_email.link_sent') }}
                    @else
                        {{ __('auth.verify_email.org_banner') }}
                        <form method="POST" action="{{ route('org.verification.send') }}" class="inline">
                            @csrf
                            <button type="submit" class="font-medium text-primary hover:underline">{{ __('auth.verify_email.resend_button') }}</button>
                        </form>
                    @endif
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
                    'destinations-saved' => __('tourism.dashboard.destinations_saved'),
                    'destination-pause-updated' => __('tourism.dashboard.destination_pause_updated'),
                    'telegram-link-refreshed' => __('tourism.dashboard.telegram_hint'),
                    'teammate-added' => __('org.team.added'),
                    'teammate-removed' => __('org.team.removed'),
                    'teammate-remove-blocked' => __('org.team.remove_blocked'),
                    'quote-template-created' => __('org.quote_templates.created'),
                    'quote-template-updated' => __('org.quote_templates.updated'),
                    'quote-template-deleted' => __('org.quote_templates.deleted'),
                    'lead-preferences-updated' => __('tourism.dashboard.lead_preferences_updated'),
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
