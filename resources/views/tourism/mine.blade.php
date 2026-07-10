@extends('layouts.app')

@section('title', __('tourism.mine.heading') . ' — Findex')

@php
    $flags = [
        'AE' => '🇦🇪', 'EG' => '🇪🇬', 'GE' => '🇬🇪', 'GR' => '🇬🇷',
        'TH' => '🇹🇭', 'CY' => '🇨🇾', 'IT' => '🇮🇹', 'FR' => '🇫🇷', 'ES' => '🇪🇸',
    ];
@endphp

@section('content')
    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.mine.heading') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ __('tourism.mine.subtitle') }}</p>
            </div>

            <a href="{{ route('tourism.request') }}" class="shrink-0 bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('tourism.mine.new_request') }}
            </a>
        </div>

        <div class="mt-8 space-y-4">
            @forelse ($quoteRequests as $quoteRequest)
                <a href="{{ route('tourism.show', $quoteRequest) }}" class="flex items-center gap-4 rounded-2xl border border-placeholder p-5 shadow-sm transition hover:border-primary/40">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">
                        {{ $flags[$quoteRequest->destination_country] ?? '✈️' }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="font-heading font-semibold text-ink">
                            {{ __('tourism.results.trip_summary', [
                                'destination' => __('destinations.' . $quoteRequest->destination_country),
                                'check_in' => $quoteRequest->check_in->translatedFormat('d M'),
                                'check_out' => $quoteRequest->check_out->translatedFormat('d M Y'),
                                'adults' => $quoteRequest->adults,
                                'children' => $quoteRequest->children,
                            ]) }}
                        </p>
                        <p class="mt-1 text-sm {{ $quoteRequest->replied_responses_count > 0 ? 'text-primary' : 'text-muted' }}">
                            {{ __('tourism.mine.replies_progress', [
                                'replied' => $quoteRequest->replied_responses_count,
                                'total' => $quoteRequest->responses_count,
                            ]) }}
                        </p>
                    </div>

                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $quoteRequest->is_open ? 'bg-primary/10 text-primary' : 'bg-placeholder/40 text-subtle' }}">
                        {{ __('tourism.mine.view') }}
                    </span>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                    <p class="text-sm text-muted">{{ __('tourism.mine.no_requests') }}</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
