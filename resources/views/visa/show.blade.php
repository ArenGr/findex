@extends('layouts.app')

@section('title', __('visa.show.heading') . ' — Findex')

@php
    $labelClass = 'block text-xs font-semibold tracking-wider text-muted uppercase';
    $card = 'rounded-2xl border border-border bg-surface p-5 sm:p-6';
    $status = $visaRequest->currentStatus();

    $facts = [
        ['label' => __('visa.request.destination'), 'value' => $visaRequest->destination_label],
        ['label' => __('visa.show.dates'), 'value' => $visaRequest->travel_from->translatedFormat('d M Y') . ' – ' . $visaRequest->travel_to->translatedFormat('d M Y')],
        ['label' => __('visa.request.applicants'), 'value' => $visaRequest->applicants],
    ];
@endphp

@section('content')
    <x-vertical-hero
        icon="ticket"
        :eyebrow="__('visa.request.eyebrow')"
        :title="__('visa.show.heading')"
        :signals="[
            ['icon' => 'building-2', 'title' => (string) $visaRequest->responses->count(), 'sub' => __('visa.show.contacted')],
            ['icon' => 'circle-check', 'title' => (string) $offers->count(), 'sub' => __('visa.show.replied')],
            ['icon' => 'clock', 'title' => (string) $pending->count(), 'sub' => __('visa.show.waiting')],
        ]"
    />

    <section class="site-container pt-9 pb-12">
        @if (session('status') === 'visa-request-submitted')
            <div class="mb-6 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary-dark">
                {{ __('visa.show.submitted_notice', ['count' => session('contacted_count', $visaRequest->responses->count())]) }}
            </div>
        @elseif (session('status') === 'visa-request-duplicate')
            <div class="mb-6 rounded-xl border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                {{ __('visa.show.duplicate_notice') }}
            </div>
        @endif

        <div class="{{ $card }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <dl class="flex flex-wrap gap-x-10 gap-y-4">
                    @foreach ($facts as $fact)
                        <div class="min-w-0">
                            <dt class="{{ $labelClass }}">{{ $fact['label'] }}</dt>
                            <dd class="mt-1 text-base font-semibold text-ink">{{ $fact['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>

                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium {{ $status->badgeClasses() }}">
                    {{ $status->label() }}
                </span>
            </div>

            @if ($visaRequest->is_open)
                <form method="POST" action="{{ $visaRequest->signedUrlFor('visa.close') }}" class="mt-5 border-t border-border pt-5">
                    @csrf
                    <button type="submit" class="btn btn-secondary">{{ __('visa.show.close_button') }}</button>
                </form>
            @else
                <p class="mt-5 border-t border-border pt-5 text-sm text-muted">{{ __('visa.show.closed_notice') }}</p>
            @endif
        </div>

        <h2 class="mt-10 font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('visa.show.offers_heading') }}</h2>

        @if ($offers->isEmpty())
            <div class="mt-5 rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
                <p class="font-heading text-base font-bold text-ink">{{ __('visa.show.empty_heading') }}</p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">{{ __('visa.show.empty_body') }}</p>
            </div>
        @else
            <div class="mt-5 flex flex-col gap-3">
                @foreach ($offers as $offer)
                    <article class="{{ $card }}">
                        <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-rates.org-mark :logo="$offer->organization->logo" :name="$offer->organization->name" />
                                <div class="min-w-0">
                                    <p class="font-heading text-base font-bold break-words text-ink">{{ $offer->organization->name }}</p>
                                    @if ($loop->first && $offers->count() > 1)
                                        <span class="mt-1 inline-block rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                                            {{ __('visa.show.cheapest') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-end gap-x-8 gap-y-3">
                                <div>
                                    <p class="{{ $labelClass }}">{{ __('visa.show.price') }}</p>
                                    <p class="mt-1 text-2xl font-bold text-primary tabular-nums">
                                        {{ number_format((float) $offer->price_amount, 2) }}
                                        <span class="text-sm font-normal text-muted">{{ $offer->price_currency }}</span>
                                    </p>
                                </div>

                                @if ($offer->processing_days)
                                    <div>
                                        <p class="{{ $labelClass }}">{{ __('visa.show.processing_days') }}</p>
                                        <p class="mt-1 text-base font-semibold text-ink">
                                            {{ trans_choice('visa.show.days', $offer->processing_days, ['count' => $offer->processing_days]) }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if ($offer->reply_text)
                            <p class="mt-4 border-t border-border pt-4 text-sm leading-relaxed break-words text-muted">{{ $offer->reply_text }}</p>
                        @endif

                        @if ($offer->valid_until)
                            <p class="mt-3 text-xs text-subtle">
                                {{ __('visa.show.valid_until') }} {{ $offer->valid_until->translatedFormat('d M Y') }}
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
