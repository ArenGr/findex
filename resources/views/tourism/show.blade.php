@extends('layouts.app')

@section('title', __('tourism.results.heading') . ' — Findex')

@php
    $flags = [
        'AE' => '🇦🇪', 'EG' => '🇪🇬', 'GE' => '🇬🇪', 'GR' => '🇬🇷',
        'TH' => '🇹🇭', 'CY' => '🇨🇾', 'IT' => '🇮🇹', 'FR' => '🇫🇷', 'ES' => '🇪🇸',
    ];

    // Replied agencies first (most recent reply first), declined ones last,
    // so a traveler sees actual quotes immediately instead of scrolling past
    // pending or declined ones.
    $statusRank = fn ($response) => match (true) {
        $response->has_replied => 2,
        $response->is_declined => 0,
        default => 1,
    };

    $sortedResponses = $quoteRequest->responses
        ->sortByDesc(fn ($response) => [$statusRank($response), $response->responded_at?->timestamp ?? 0])
        ->values();

    $repliedCount = $sortedResponses->where('has_replied', true)->count();

    // Data the comparison table needs, available to Alpine without a
    // round trip - everything's already loaded on this one page.
    $comparableData = $sortedResponses
        ->where('has_replied', true)
        ->map(fn ($response) => [
            'id' => $response->id,
            'name' => $response->organization->name,
            'initials' => Str::of($response->organization->name)->substr(0, 2)->upper()->toString(),
            'logo' => $response->organization->logo,
            'price' => $response->price_amount
                ? rtrim(rtrim((string) $response->price_amount, '0'), '.') . ' ' . $response->price_currency
                : null,
            'hotel' => $response->offered_hotel_name,
            'flight' => $response->flight_details,
            'inclusions' => $response->inclusions,
            'notes' => $response->reply_text,
        ])
        ->values();
@endphp

@section('content')
    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10" x-data="{ selected: [], comparable: @js($comparableData) }">
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

        @if ($repliedCount >= 2)
            <p class="mt-6 text-sm text-muted">{{ __('tourism.results.compare_hint') }}</p>
        @endif

        <div class="mt-4 space-y-4">
            @forelse ($sortedResponses as $response)
                <div
                    class="rounded-2xl border p-5 shadow-sm transition {{ $response->is_declined ? 'opacity-60' : '' }}"
                    @if ($response->has_replied)
                        :class="selected.includes({{ $response->id }}) ? 'border-primary ring-2 ring-primary/20' : 'border-placeholder'"
                    @else
                        :class="'border-placeholder'"
                    @endif
                >
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
                        @elseif ($response->is_declined)
                            <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-subtle">
                                {{ __('tourism.results.declined_label') }}
                            </span>
                        @else
                            <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-muted">
                                <span class="h-1.5 w-1.5 motion-safe:animate-pulse rounded-full bg-subtle"></span>
                                {{ __('tourism.results.waiting_label') }}
                            </span>
                        @endif
                    </div>

                    @if ($response->has_replied)
                        @if ($response->price_amount)
                            <p class="mt-3 font-heading text-xl font-bold text-primary">
                                {{ rtrim(rtrim((string) $response->price_amount, '0'), '.') }} {{ $response->price_currency }}
                            </p>
                        @endif

                        <dl class="mt-2 space-y-1 text-sm text-ink">
                            @if ($response->offered_hotel_name)
                                <div><dt class="inline text-subtle">{{ __('tourism.results.hotel_label') }}:</dt> <dd class="inline">{{ $response->offered_hotel_name }}</dd></div>
                            @endif
                            @if ($response->flight_details)
                                <div><dt class="inline text-subtle">{{ __('tourism.results.flight_label') }}:</dt> <dd class="inline">{{ $response->flight_details }}</dd></div>
                            @endif
                            @if ($response->inclusions)
                                <div><dt class="inline text-subtle">{{ __('tourism.results.inclusions_label') }}:</dt> <dd class="inline">{{ $response->inclusions }}</dd></div>
                            @endif
                        </dl>

                        @if ($response->reply_text)
                            <p class="mt-2 rounded-xl bg-primary/5 px-4 py-3 text-sm leading-relaxed text-ink">{{ $response->reply_text }}</p>
                        @endif

                        @if ($response->attachment_path)
                            <a href="{{ Storage::url($response->attachment_path) }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-medium text-primary hover:underline">
                                {{ __('tourism.results.attachment_label') }} &darr;
                            </a>
                        @endif

                        @if ($repliedCount >= 2)
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-ink">
                                <input
                                    type="checkbox"
                                    value="{{ $response->id }}"
                                    x-model.number="selected"
                                    :disabled="!selected.includes({{ $response->id }}) && selected.length >= 3"
                                    class="rounded border-border-muted text-primary focus:ring-primary disabled:opacity-40"
                                >
                                {{ __('tourism.results.add_to_compare') }}
                            </label>
                        @endif
                    @elseif ($response->is_declined)
                        <p class="mt-4 text-sm text-subtle">{{ __('tourism.results.declined_hint') }}</p>
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

        {{-- Sticky compare bar --}}
        <div
            x-show="selected.length >= 2"
            x-cloak
            x-transition
            class="sticky bottom-4 mt-6 flex items-center justify-between gap-4 rounded-2xl border border-primary/30 bg-white p-4 shadow-lg"
        >
            <span class="text-sm font-medium text-ink">
                <span x-text="selected.length"></span> {{ __('tourism.results.quotes_selected') }}
            </span>
            <div class="flex items-center gap-4">
                <button type="button" @click="selected = []" class="text-xs font-medium text-subtle hover:text-ink">
                    {{ __('tourism.results.compare_bar_clear') }}
                </button>
                <a href="#compare-table" class="bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                    {{ __('tourism.results.compare_bar_button') }}
                </a>
            </div>
        </div>

        <p x-show="selected.length >= 3" x-cloak class="mt-2 text-center text-xs text-subtle">
            {{ __('tourism.results.compare_max_reached') }}
        </p>

        {{-- Side-by-side comparison table --}}
        <div x-show="selected.length >= 2" x-cloak id="compare-table" class="mt-10 scroll-mt-24">
            <h2 class="font-heading text-lg font-semibold text-ink">{{ __('tourism.results.compare_heading') }}</h2>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-placeholder">
                <table class="w-full min-w-[480px] border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="w-36 shrink-0"></th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <th class="border-b border-placeholder bg-placeholder/10 px-4 py-4 text-left align-bottom">
                                    <div class="flex items-center gap-2">
                                        <template x-if="item.logo">
                                            <img :src="item.logo" alt="" class="h-8 w-8 shrink-0 rounded-full object-contain">
                                        </template>
                                        <template x-if="!item.logo">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white" x-text="item.initials"></span>
                                        </template>
                                        <span class="font-semibold text-ink" x-text="item.name"></span>
                                    </div>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.compare_row_price') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4">
                                    <span x-show="item.price" class="font-heading text-lg font-bold text-primary" x-text="item.price"></span>
                                    <span x-show="!item.price" class="text-subtle">{{ __('tourism.results.compare_no_price') }}</span>
                                </td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.hotel_label') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4 text-ink" x-text="item.hotel || '—'"></td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.flight_label') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4 text-ink" x-text="item.flight || '—'"></td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.inclusions_label') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4 text-ink" x-text="item.inclusions || '—'"></td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left align-top text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.results.compare_row_reply') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="max-w-[240px] border-t border-placeholder px-4 py-4 align-top text-sm leading-relaxed text-ink" x-text="item.notes || '—'"></td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-subtle">{{ __('tourism.results.bookmark_hint') }}</p>
    </section>
@endsection
