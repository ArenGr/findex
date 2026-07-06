@extends('layouts.dashboard')

@section('title', __('org.reports.title'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.reports.title') }}</h1>

    <p class="mt-2 text-sm text-muted">
        {{ $reportRequest->branch?->name ?? __('org.reports.all_branches') }}
        @if ($reportRequest->period_from || $reportRequest->period_to)
            · {{ $reportRequest->period_from?->format('Y-m-d') }} — {{ $reportRequest->period_to?->format('Y-m-d') }}
        @endif
    </p>

    <p class="mt-4 inline-block border border-placeholder px-3 py-1 text-sm text-ink">
        {{ __('org.reports.status.' . $reportRequest->status) }}
    </p>

    @if ($reportRequest->status === 'failed')
        <p class="mt-4 text-sm text-red-600">{{ $reportRequest->error_message }}</p>
    @endif

    @if ($reportRequest->status === 'completed' && $reportRequest->report)
        @php $report = $reportRequest->report; @endphp

        @if ($report->review_count === 0)
            <p class="mt-6 text-sm text-muted">{{ __('org.reports.no_reviews_in_period') }}</p>
        @else
            <div class="mt-6 grid grid-cols-3 gap-4">
                <div class="border border-placeholder p-5">
                    <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.reports.positive') }}</p>
                    <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $report->positive_pct }}%</p>
                </div>
                <div class="border border-placeholder p-5">
                    <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.reports.neutral') }}</p>
                    <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $report->neutral_pct }}%</p>
                </div>
                <div class="border border-placeholder p-5">
                    <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.reports.negative') }}</p>
                    <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $report->negative_pct }}%</p>
                </div>
            </div>

            <p class="mt-4 text-xs text-subtle">{{ __('org.reports.review_count') }}: {{ $report->review_count }}</p>

            @if ($report->summary)
                <h2 class="mt-8 font-heading text-lg font-semibold text-ink">{{ __('org.reports.summary') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-body-text">{{ $report->summary }}</p>
            @endif

            @if (!empty($report->themes))
                <h2 class="mt-8 font-heading text-lg font-semibold text-ink">{{ __('org.reports.themes') }}</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-body-text">
                    @foreach ($report->themes as $theme)
                        <li>{{ $theme }}</li>
                    @endforeach
                </ul>
            @endif
        @endif
    @endif
@endsection
