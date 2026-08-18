@extends('layouts.dashboard')

@section('title', __('tourism.inbox.heading'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.inbox.heading') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('tourism.inbox.subheading') }}</p>

    <div class="mt-6 flex gap-6 border-b border-placeholder">
        @foreach (['open' => __('tourism.inbox.tab_open', ['count' => $openCount]), 'answered' => __('tourism.inbox.tab_answered')] as $key => $label)
            <a
                href="{{ route('org.dashboard.travel-requests.index', ['tab' => $key]) }}"
                @class([
                    'pb-2 text-sm transition',
                    'border-b-2 border-primary font-semibold text-primary' => $tab === $key,
                    'text-muted hover:text-ink' => $tab !== $key,
                ])
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($responses as $response)
            @php $quoteRequest = $response->quoteRequest; @endphp

            <a
                href="{{ route('org.dashboard.travel-requests.show', $response) }}"
                class="block rounded-2xl border border-placeholder p-5 transition hover:border-primary/40"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <x-trip-brief :request="$quoteRequest" compact />
                    </div>

                    <div class="flex shrink-0 flex-col items-start gap-2 sm:w-44 sm:items-end">
                        @if ($response->has_replied)
                            <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                {{ trans_choice('tourism.inbox.offers_sent', $response->suggestions->count(), ['count' => $response->suggestions->count()]) }}
                            </span>

                            @if ($response->is_expired)
                                <span class="text-xs text-subtle">{{ __('tourism.offers.expired_badge') }}</span>
                            @elseif ($response->valid_until)
                                <span class="text-xs text-muted">{{ __('tourism.offers.valid_until', ['date' => $response->valid_until->translatedFormat('d M, H:i')]) }}</span>
                            @endif
                        @elseif ($response->is_declined)
                            <span class="rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-subtle">
                                {{ __('tourism.results.declined_label') }}
                            </span>
                        @else
                            <span class="rounded-full bg-accent-yellow/20 px-3 py-1 text-xs font-semibold text-ink">
                                {{ __('tourism.inbox.needs_answer') }}
                            </span>
                        @endif

                        @if ($quoteRequest->is_open)
                            <span class="text-xs text-subtle">{{ __('tourism.inbox.closes_in', ['time' => $quoteRequest->closes_in]) }}</span>
                        @else
                            <span class="text-xs text-subtle">{{ $quoteRequest->currentStatus()->label() }}</span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-placeholder p-10 text-center">
                <p class="text-sm text-muted">
                    {{ $tab === 'answered' ? __('tourism.inbox.empty_answered') : __('tourism.inbox.empty_open') }}
                </p>

                @if ($tab !== 'answered')
                    <a href="{{ route('org.dashboard.tourism.index') }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">
                        {{ __('tourism.inbox.empty_open_cta') }} &rarr;
                    </a>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $responses->links() }}
    </div>
@endsection
