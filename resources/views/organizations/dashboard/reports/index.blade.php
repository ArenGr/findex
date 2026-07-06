@extends('layouts.dashboard')

@section('title', __('org.reports.title'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.reports.title') }}</h1>
        <a href="{{ route('org.dashboard.reports.create') }}" class="bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.reports.request') }}
        </a>
    </div>

    <div class="mt-6 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($reportRequests as $reportRequest)
            <div class="flex items-center justify-between py-4 text-sm">
                <div>
                    <p class="text-ink">{{ $reportRequest->created_at->translatedFormat('d F, Y') }}</p>
                    <p class="text-xs text-muted">
                        {{ $reportRequest->branch?->name ?? __('org.reports.all_branches') }}
                    </p>
                </div>
                <span class="text-muted">{{ __('org.reports.status.' . $reportRequest->status) }}</span>
                <a href="{{ route('org.dashboard.reports.show', $reportRequest) }}" class="font-medium text-primary hover:underline">
                    {{ __('org.reports.view') }}
                </a>
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('org.reports.no_requests') }}</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $reportRequests->links() }}
    </div>
@endsection
