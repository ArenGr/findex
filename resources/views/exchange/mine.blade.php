@extends('layouts.app')

@section('title', __('exchange_quotes.mine.heading') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('exchange_quotes.mine.heading') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ __('exchange_quotes.mine.subtitle') }}</p>
            </div>

            <a href="{{ route('exchange.request') }}" class="shrink-0 bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('exchange_quotes.mine.new_request') }}
            </a>
        </div>

        <div class="mt-8 space-y-4">
            @forelse ($exchangeQuoteRequests as $exchangeQuoteRequest)
                <a href="{{ route('exchange.show', $exchangeQuoteRequest) }}" class="flex items-center gap-4 rounded-2xl border border-placeholder p-5 shadow-sm transition hover:border-primary/40">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">
                        💱
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="font-heading font-semibold text-ink">
                            {{ number_format((float) $exchangeQuoteRequest->amount, 2) }} {{ $exchangeQuoteRequest->currency->code }}
                            &middot;
                            {{ __('exchange_quotes.request.direction_' . $exchangeQuoteRequest->rate_field, ['currency' => $exchangeQuoteRequest->currency->code]) }}
                        </p>
                        <p class="mt-1 text-sm {{ $exchangeQuoteRequest->replied_responses_count > 0 ? 'text-primary' : 'text-muted' }}">
                            {{ __('exchange_quotes.mine.replies_progress', [
                                'replied' => $exchangeQuoteRequest->replied_responses_count,
                                'total' => $exchangeQuoteRequest->responses_count,
                            ]) }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $exchangeQuoteRequest->is_open ? 'bg-primary/10 text-primary' : 'bg-placeholder/40 text-subtle' }}">
                            {{ __('exchange_quotes.mine.view') }}
                        </span>
                        @if ($exchangeQuoteRequest->is_open)
                            <span class="text-xs text-subtle">{{ $exchangeQuoteRequest->closes_in }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                    <p class="text-sm text-muted">{{ __('exchange_quotes.mine.no_requests') }}</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
