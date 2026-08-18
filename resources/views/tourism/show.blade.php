@extends('layouts.app')

@section('title', __('tourism.status_page.heading') . ' — Findex')

@php
    use App\Enums\QuoteRequestStatus;

    $status = $quoteRequest->currentStatus();

    // The four steps a request moves through, and how far along it is. Every
    // one is read off persisted state (see QuoteRequest::scopeWithProgressCounts
    // and QuoteResponse::markViewed) - nothing here advances on a timer or
    // fills in a step nobody has actually taken.
    $steps = [
        [
            'label' => __('tourism.status_page.step_submitted'),
            'done' => true,
        ],
        [
            'label' => __('tourism.status_page.step_reviewing', ['count' => $quoteRequest->reviewing_count]),
            'done' => $quoteRequest->reviewing_count > 0 || $quoteRequest->responded_count > 0,
        ],
        [
            'label' => __('tourism.status_page.step_offers', ['count' => $quoteRequest->offers_count]),
            'done' => $quoteRequest->offers_count > 0,
        ],
        [
            'label' => __('tourism.status_page.step_choosing'),
            'done' => $quoteRequest->offers->contains(fn ($offer) => $offer->is_selected),
        ],
    ];

    $completed = collect($steps)->where('done', true)->count();
@endphp

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-16 lg:px-10">
        <a href="{{ route('tourism.mine') }}" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path fill-rule="evenodd" d="M12.7 15.7a1 1 0 0 1-1.4 0l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 1 1 1.4 1.4L8.4 10l4.3 4.3a1 1 0 0 1 0 1.4Z" clip-rule="evenodd" />
            </svg>
            {{ __('tourism.status_page.back_to_requests') }}
        </a>

        @if (session('status') === 'quote-request-submitted')
            <div class="mt-4 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.status_page.just_submitted', ['count' => session('contacted_count', $quoteRequest->contacted_count)]) }}
            </div>
        @endif

        @if (session('status') === 'quote-request-duplicate')
            <div class="mt-4 rounded-lg border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                {{ __('tourism.status_page.already_submitted') }}
            </div>
        @endif

        @if (session('status') === 'request-closed')
            <div class="mt-4 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm text-ink">
                {{ __('tourism.status_page.closed_confirmation') }}
            </div>
        @endif

        <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.status_page.heading') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ __('tourism.status_page.subheading') }}</p>
            </div>

            <span class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold {{ $status->badgeClasses() }}">
                {{ $status->label() }}
            </span>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px] lg:items-start">
            <div class="space-y-6">
                {{-- Trip brief --}}
                <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                    <x-trip-brief :request="$quoteRequest" />
                </div>

                {{-- Progress --}}
                <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                    <h2 class="font-heading text-base font-semibold text-ink">{{ __('tourism.status_page.progress_heading') }}</h2>

                    {{-- The filled track stops at the last completed step, so
                    it can never run ahead of what has actually happened. --}}
                    <div class="relative mt-6 pb-2">
                        <div class="absolute top-2 right-2 left-2 h-1 rounded-full bg-placeholder/60"></div>
                        <div
                            class="absolute top-2 left-2 h-1 rounded-full bg-primary transition-all duration-700"
                            style="width: calc({{ $completed > 1 ? (($completed - 1) / (count($steps) - 1)) * 100 : 0 }}% - {{ $completed > 1 ? '0px' : '0px' }})"
                        ></div>

                        <ol class="relative flex justify-between gap-2">
                            @foreach ($steps as $step)
                                <li class="flex flex-1 flex-col items-center gap-2 text-center">
                                    <span class="h-4 w-4 shrink-0 rounded-full ring-4 ring-white {{ $step['done'] ? 'bg-primary' : 'border-2 border-placeholder bg-white' }}"></span>
                                    <span class="text-xs {{ $step['done'] ? 'font-medium text-primary' : 'text-muted' }}">{{ $step['label'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>

                {{-- Activity --}}
                <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                    <h2 class="font-heading text-base font-semibold text-ink">{{ __('tourism.status_page.activity_heading') }}</h2>

                    @if ($quoteRequest->contacted_count === 0)
                        {{-- Only reachable if the fan-out job hasn't run yet:
                        store() refuses to create a request with no matching
                        agency at all (see the no_partners_for_destination
                        error), so this is "not sent yet", not "nobody wants it". --}}
                        <p class="mt-3 text-sm text-muted">{{ __('tourism.status_page.no_agencies_yet') }}</p>
                    @else
                        <ul class="mt-4 space-y-3">
                            @if ($quoteRequest->responded_count > 0)
                                <li class="flex items-start gap-3">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                                    <p class="text-sm text-ink">
                                        {{ __('tourism.status_page.activity_responded', [
                                            'agencies' => $quoteRequest->responded_count,
                                            'offers' => $quoteRequest->offers_count,
                                        ]) }}
                                    </p>
                                </li>
                            @endif

                            @if ($quoteRequest->reviewing_count > 0)
                                <li class="flex items-start gap-3">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-accent-yellow"></span>
                                    <p class="text-sm text-ink">
                                        {{ __('tourism.status_page.activity_reviewing', ['count' => $quoteRequest->reviewing_count]) }}
                                    </p>
                                </li>
                            @endif

                            <li class="flex items-start gap-3">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-placeholder"></span>
                                <p class="text-sm text-muted">
                                    {{ __('tourism.status_page.activity_contacted', ['count' => $quoteRequest->contacted_count]) }}
                                </p>
                            </li>
                        </ul>
                    @endif

                    @if ($quoteRequest->offers_count > 0)
                        <a
                            href="{{ $offersUrl }}"
                            class="mt-6 inline-flex items-center gap-2 bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:bg-primary-dark"
                        >
                            {{ __('tourism.status_page.view_offers', ['count' => $quoteRequest->offers_count]) }}
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    @elseif ($status->isOpen())
                        {{-- The honest empty state: contacted, nobody has
                        answered yet. No spinner pretending work is happening. --}}
                        <p class="mt-6 rounded-lg bg-primary/5 px-4 py-3 text-sm text-muted">
                            {{ __('tourism.status_page.waiting_for_offers') }}
                        </p>
                    @else
                        <p class="mt-6 rounded-lg bg-placeholder/20 px-4 py-3 text-sm text-muted">
                            {{ __('tourism.status_page.no_offers_received') }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                    <h3 class="font-heading text-sm font-semibold text-ink">{{ __('tourism.status_page.next_heading') }}</h3>
                    <ol class="mt-3 space-y-3">
                        @foreach (['next_1', 'next_2', 'next_3'] as $index => $key)
                            <li class="flex gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-placeholder/40 text-xs font-semibold text-ink">{{ $index + 1 }}</span>
                                <p class="text-sm text-muted">{{ __('tourism.status_page.' . $key) }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>

                @if ($status->isOpen())
                    <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                        <p class="text-xs text-muted">
                            {{ __('tourism.results.expires_note', [
                                'date' => $quoteRequest->expires_at->translatedFormat('d F Y'),
                                'countdown' => $quoteRequest->closes_in,
                            ]) }}
                        </p>

                        <form
                            method="POST"
                            action="{{ $quoteRequest->signedUrlFor('tourism.close') }}"
                            class="mt-4"
                            onsubmit="return confirm('{{ __('tourism.status_page.close_confirm') }}')"
                        >
                            @csrf
                            <button type="submit" class="text-sm font-medium text-muted underline hover:text-ink">
                                {{ __('tourism.status_page.close_request') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
