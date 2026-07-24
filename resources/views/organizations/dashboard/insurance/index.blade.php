@extends('layouts.dashboard')

@section('title', __('org.insurance.title'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.insurance.title') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('org.insurance.subtitle') }}</p>

    @if ($totalQuotes > 0)
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
            <div class="border border-placeholder p-5">
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.insurance.total_quotes') }}</p>
                <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $totalQuotes }}</p>
            </div>
            <div class="border border-placeholder p-5">
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.insurance.interested_count') }}</p>
                <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $interestedCount }}</p>
            </div>
            <div class="border border-placeholder p-5">
                <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('org.insurance.interested_rate') }}</p>
                <p class="mt-2 font-heading text-2xl font-bold text-ink">{{ $interestedRate !== null ? $interestedRate . '%' : '—' }}</p>
            </div>
        </div>
    @endif

    <h2 class="mt-10 font-heading text-lg font-semibold text-ink">{{ __('org.insurance.quotes_heading') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('org.insurance.quotes_hint') }}</p>

    <div class="mt-4 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($quotes as $quote)
            <div class="py-4 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <span class="font-medium text-ink">
                        {{ __('org.insurance.vehicle_summary', [
                            'plate' => $quote->vehicle_plate,
                            'term' => __('auto_insurance.request.contract_terms.' . $quote->policy_term_months),
                        ]) }}
                    </span>
                    <span class="shrink-0 text-subtle">{{ \Illuminate\Support\Carbon::parse($quote->created_at)->diffForHumans() }}</span>
                </div>

                <p class="mt-1 text-xs text-muted">{{ $quote->requester_name }} &middot; {{ $quote->requester_email }}</p>

                <p class="mt-2 font-heading text-lg font-bold text-primary">
                    {{ rtrim(rtrim((string) $quote->premium_amount, '0'), '.') }} {{ $quote->premium_currency }}
                </p>

                @if ($quote->is_interested)
                    <p class="mt-1 text-xs font-medium text-primary">✓ {{ __('org.insurance.interested_label', ['time' => \Illuminate\Support\Carbon::parse($quote->interested_at)->diffForHumans()]) }}</p>
                @endif
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('org.insurance.no_quotes_yet') }}</p>
        @endforelse
    </div>
@endsection
