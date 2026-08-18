@extends('layouts.app')

@section('title', __('tourism.mine.heading') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-16 lg:px-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.mine.heading') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ __('tourism.mine.subtitle') }}</p>
            </div>

            <a href="{{ route('tourism.request') }}" class="shrink-0 bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('tourism.mine.new_request') }}
            </a>
        </div>

        <div class="mt-8 flex gap-6 border-b border-placeholder">
            @foreach (['active' => __('tourism.mine.tab_active', ['count' => $activeCount]), 'past' => __('tourism.mine.tab_past')] as $key => $label)
                <a
                    href="{{ route('tourism.mine', ['tab' => $key]) }}"
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
            @forelse ($quoteRequests as $quoteRequest)
                @php $status = $quoteRequest->currentStatus(); @endphp

                <article class="rounded-2xl border border-placeholder bg-white p-5 shadow-sm transition hover:border-primary/40">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <x-trip-brief :request="$quoteRequest" compact />
                        </div>

                        <div class="flex shrink-0 flex-col items-start gap-3 border-placeholder sm:w-48 sm:items-end sm:border-l sm:pl-5">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $status->badgeClasses() }}">
                                {{ $status->label() }}
                            </span>

                            @if ($quoteRequest->offers_count > 0)
                                <p class="text-sm text-ink sm:text-right">
                                    {!! __('tourism.mine.offers_from_agencies', [
                                        'offers' => '<strong class="font-semibold">' . $quoteRequest->offers_count . '</strong>',
                                        'agencies' => $quoteRequest->responded_count,
                                    ]) !!}
                                </p>

                                <a href="{{ route('tourism.offers', $quoteRequest) }}" class="w-full bg-primary px-4 py-2 text-center text-sm font-medium text-white hover:bg-primary-dark">
                                    {{ __('tourism.status_page.view_offers', ['count' => $quoteRequest->offers_count]) }}
                                </a>
                            @else
                                <p class="text-sm text-muted sm:text-right">
                                    {{ __('tourism.mine.awaiting_offers', ['count' => $quoteRequest->contacted_count]) }}
                                </p>

                                <a href="{{ route('tourism.show', $quoteRequest) }}" class="w-full border border-placeholder px-4 py-2 text-center text-sm font-medium text-ink hover:bg-placeholder/20">
                                    {{ __('tourism.mine.view') }}
                                </a>
                            @endif

                            @if ($status->isOpen())
                                <span class="text-xs text-subtle">{{ $quoteRequest->closes_in }}</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-placeholder p-10 text-center">
                    <p class="text-sm text-muted">
                        {{ $tab === 'past' ? __('tourism.mine.no_past_requests') : __('tourism.mine.no_requests') }}
                    </p>

                    @if ($tab !== 'past')
                        <a href="{{ route('tourism.request') }}" class="mt-4 inline-block bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
                            {{ __('tourism.mine.new_request') }}
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $quoteRequests->links() }}
        </div>
    </section>
@endsection
