@extends('layouts.dashboard')

@section('title', __('org.overview.title'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.overview.title') }}</h1>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="border border-placeholder p-5">
            <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.overview.reviews_count') }}</p>
            <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $reviewsCount }}</p>
        </div>
        <div class="border border-placeholder p-5">
            <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.overview.average_rating') }}</p>
            <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $averageRating ? number_format($averageRating, 1) : '—' }}</p>
        </div>
        <div class="border border-placeholder p-5">
            <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.overview.unreplied') }}</p>
            <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $unrepliedCount }}</p>
        </div>
        <div class="border border-placeholder p-5">
            <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.overview.branches_count') }}</p>
            <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $branchesCount }}</p>
        </div>
    </div>

    <h2 class="mt-10 font-heading text-lg font-semibold text-ink">{{ __('org.overview.recent_reports') }}</h2>

    <div class="mt-4 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($recentReportRequests as $reportRequest)
            <div class="flex items-center justify-between py-4 text-sm">
                <span class="text-ink">{{ $reportRequest->created_at->translatedFormat('d F, Y') }}</span>
                <span class="text-muted">{{ __('org.reports.status.' . $reportRequest->status) }}</span>
                <a href="{{ route('org.dashboard.reports.show', $reportRequest) }}" class="font-medium text-primary hover:underline">
                    {{ __('org.reports.view') }}
                </a>
            </div>
        @empty
            <p class="py-4 text-sm text-muted">{{ __('org.overview.no_recent_reports') }}</p>
        @endforelse
    </div>
@endsection
