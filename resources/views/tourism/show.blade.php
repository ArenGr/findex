@extends('layouts.app')

@section('title', __('tourism.results.heading') . ' — Findex')

@php
    $flags = [
        'AE' => '🇦🇪', 'EG' => '🇪🇬', 'GE' => '🇬🇪', 'GR' => '🇬🇷',
        'TH' => '🇹🇭', 'CY' => '🇨🇾', 'IT' => '🇮🇹', 'FR' => '🇫🇷', 'ES' => '🇪🇸',
    ];
@endphp

@section('content')
    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
        @if (session('status') === 'quote-request-submitted')
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.results.submitted', ['count' => $quoteRequest->responses->count()]) }}
            </div>
        @endif

        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.results.heading') }}</h1>

        {{-- Trip summary "ticket" --}}
        <div class="mt-6 rounded-2xl border border-placeholder p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">
                    {{ $flags[$quoteRequest->destination_country] ?? '✈️' }}
                </span>
                <div class="min-w-0">
                    <p class="font-heading font-semibold text-ink">
                        {{ __('tourism.results.trip_summary', [
                            'destination' => __('destinations.' . $quoteRequest->destination_country),
                            'check_in' => $quoteRequest->check_in->translatedFormat('d M'),
                            'check_out' => $quoteRequest->check_out->translatedFormat('d M Y'),
                            'adults' => $quoteRequest->adults,
                            'children' => $quoteRequest->children,
                        ]) }}
                    </p>
                    @if ($quoteRequest->hotel_name)
                        <p class="mt-0.5 truncate text-sm text-muted">{{ $quoteRequest->hotel_name }}</p>
                    @endif
                </div>
            </div>

            <p class="mt-3 border-t border-placeholder pt-3 text-xs {{ $quoteRequest->is_open ? 'text-primary' : 'text-subtle' }}">
                @if ($quoteRequest->is_open)
                    {{ __('tourism.results.expires_note', ['date' => $quoteRequest->expires_at->translatedFormat('d F Y')]) }}
                @else
                    {{ __('tourism.results.closed_note', ['date' => $quoteRequest->expires_at->translatedFormat('d F Y')]) }}
                @endif
            </p>
        </div>

        <div class="mt-8 space-y-4">
            @forelse ($quoteRequest->responses as $response)
                <div class="rounded-2xl border border-placeholder p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            @if ($response->organization->logo)
                                <img src="{{ $response->organization->logo }}" alt="{{ $response->organization->name }}" class="h-10 w-10 rounded-full object-contain">
                            @else
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                                    {{ Str::of($response->organization->name)->substr(0, 2)->upper() }}
                                </div>
                            @endif
                            <span class="font-medium text-ink">{{ $response->organization->name }}</span>
                        </div>

                        @if ($response->has_replied)
                            <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                                {{ __('tourism.results.replied_label', ['time' => $response->responded_at->diffForHumans()]) }}
                            </span>
                        @else
                            <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-muted">
                                <span class="h-1.5 w-1.5 motion-safe:animate-pulse rounded-full bg-subtle"></span>
                                {{ __('tourism.results.waiting_label') }}
                            </span>
                        @endif
                    </div>

                    @if ($response->reply_text)
                        <p class="mt-4 rounded-xl bg-primary/5 px-4 py-3 text-sm leading-relaxed text-ink">{{ $response->reply_text }}</p>
                    @else
                        <p class="mt-4 text-sm text-subtle">{{ __('tourism.results.no_reply_yet') }}</p>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                    <p class="text-sm text-muted">{{ __('tourism.results.no_responses_yet') }}</p>
                </div>
            @endforelse
        </div>

        <p class="mt-8 text-center text-xs text-subtle">{{ __('tourism.results.bookmark_hint') }}</p>
    </section>
@endsection
