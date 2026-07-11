@extends('layouts.app')

@section('title', __('auto_insurance.results.heading') . ' — Findex')

@php
    $sortedQuotes = $autoInsuranceRequest->quotes
        ->sortBy(fn ($quote) => [
            $quote->is_declined ? 1 : 0,
            $quote->premium_amount !== null ? (float) $quote->premium_amount : PHP_FLOAT_MAX,
        ])
        ->values();

    $cheapestQuoteId = $sortedQuotes->firstWhere('is_declined', false)?->id;

    // Data the comparison table needs, available to Alpine without a round
    // trip - everything's already loaded on this one page.
    $comparableData = $sortedQuotes
        ->where('is_declined', false)
        ->map(fn ($quote) => [
            'id' => $quote->id,
            'name' => $quote->organization->name,
            'initials' => Str::of($quote->organization->name)->substr(0, 2)->upper()->toString(),
            'logo' => $quote->organization->logo,
            'premium' => $quote->premium_amount
                ? rtrim(rtrim((string) $quote->premium_amount, '0'), '.') . ' ' . $quote->premium_currency
                : null,
            'coverage' => $quote->coverage_summary,
            'notes' => $quote->notes,
        ])
        ->values();

    $quotedCount = $sortedQuotes->where('is_declined', false)->count();
@endphp

@section('content')
    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10" x-data="{ selected: [], comparable: @js($comparableData) }">
        @if (session('status') === 'insurance-request-submitted')
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('auto_insurance.results.submitted', ['count' => $quotedCount]) }}
            </div>
        @endif

        <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('auto_insurance.results.heading') }}</h1>

        {{-- Vehicle summary "ticket" --}}
        <div class="mt-6 rounded-2xl border border-placeholder p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">
                    🚗
                </span>
                <div class="min-w-0">
                    <p class="font-heading font-semibold text-ink">
                        {{ __('auto_insurance.results.vehicle_summary', [
                            'plate' => $autoInsuranceRequest->vehicle_plate,
                            'term' => __('auto_insurance.request.contract_terms.' . $autoInsuranceRequest->contract_term_months),
                        ]) }}
                    </p>
                    <p class="mt-0.5 truncate text-sm text-muted">{{ __('auto_insurance.request.owner_types.' . $autoInsuranceRequest->owner_type) }}</p>
                </div>
            </div>
        </div>

        @if ($quotedCount >= 2)
            <p class="mt-6 text-sm text-muted">{{ __('auto_insurance.results.compare_hint') }}</p>
        @endif

        <div class="mt-4 space-y-4">
            @forelse ($sortedQuotes as $quote)
                <div
                    class="rounded-2xl border p-5 shadow-sm transition {{ $quote->is_declined ? 'opacity-60' : '' }}"
                    :class="selected.includes({{ $quote->id }}) ? 'border-primary ring-2 ring-primary/20' : 'border-placeholder'"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            @if ($quote->organization->logo)
                                <img src="{{ $quote->organization->logo }}" alt="{{ $quote->organization->name }}" class="h-10 w-10 rounded-full object-contain">
                            @else
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                                    {{ Str::of($quote->organization->name)->substr(0, 2)->upper() }}
                                </div>
                            @endif
                            <span class="font-medium text-ink">{{ $quote->organization->name }}</span>
                        </div>

                        @if (!$quote->is_declined && $quote->id === $cheapestQuoteId)
                            <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                {{ __('auto_insurance.results.best_price_badge') }}
                            </span>
                        @elseif ($quote->is_declined)
                            <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-subtle">
                                {{ __('auto_insurance.results.declined_label') }}
                            </span>
                        @endif
                    </div>

                    @if (!$quote->is_declined)
                        <p class="mt-3 font-heading text-xl font-bold text-primary">
                            {{ rtrim(rtrim((string) $quote->premium_amount, '0'), '.') }} {{ $quote->premium_currency }}
                            <span class="text-sm font-normal text-subtle">/ {{ __('auto_insurance.results.term_months', ['months' => $quote->policy_term_months]) }}</span>
                        </p>

                        <dl class="mt-2 space-y-1 text-sm text-ink">
                            @if ($quote->coverage_summary)
                                <div><dt class="inline text-subtle">{{ __('auto_insurance.results.coverage_summary_label') }}:</dt> <dd class="inline">{{ $quote->coverage_summary }}</dd></div>
                            @endif
                        </dl>

                        @if ($quote->notes)
                            <p class="mt-2 rounded-xl bg-primary/5 px-4 py-3 text-sm leading-relaxed text-ink">{{ $quote->notes }}</p>
                        @endif

                        @if ($quotedCount >= 2)
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-ink">
                                <input
                                    type="checkbox"
                                    value="{{ $quote->id }}"
                                    x-model.number="selected"
                                    :disabled="!selected.includes({{ $quote->id }}) && selected.length >= 3"
                                    class="rounded border-border-muted text-primary focus:ring-primary disabled:opacity-40"
                                >
                                {{ __('auto_insurance.results.add_to_compare') }}
                            </label>
                        @endif
                    @else
                        <p class="mt-4 text-sm text-subtle">{{ __('auto_insurance.results.declined_hint') }}</p>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                    <p class="text-sm text-muted">{{ __('auto_insurance.results.no_quotes_yet') }}</p>
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
                <span x-text="selected.length"></span> {{ __('auto_insurance.results.quotes_selected') }}
            </span>
            <div class="flex items-center gap-4">
                <button type="button" @click="selected = []" class="text-xs font-medium text-subtle hover:text-ink">
                    {{ __('auto_insurance.results.compare_bar_clear') }}
                </button>
                <a href="#compare-table" class="bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                    {{ __('auto_insurance.results.compare_bar_button') }}
                </a>
            </div>
        </div>

        <p x-show="selected.length >= 3" x-cloak class="mt-2 text-center text-xs text-subtle">
            {{ __('auto_insurance.results.compare_max_reached') }}
        </p>

        {{-- Side-by-side comparison table --}}
        <div x-show="selected.length >= 2" x-cloak id="compare-table" class="mt-10 scroll-mt-24">
            <h2 class="font-heading text-lg font-semibold text-ink">{{ __('auto_insurance.results.compare_heading') }}</h2>

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
                                {{ __('auto_insurance.results.compare_row_premium') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="border-t border-placeholder px-4 py-4">
                                    <span class="font-heading text-lg font-bold text-primary" x-text="item.premium"></span>
                                </td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left align-top text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('auto_insurance.results.compare_row_coverage') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="max-w-[240px] border-t border-placeholder px-4 py-4 align-top text-sm leading-relaxed text-ink" x-text="item.coverage || '—'"></td>
                            </template>
                        </tr>
                        <tr>
                            <th class="px-4 py-4 text-left align-top text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('auto_insurance.results.compare_row_notes') }}
                            </th>
                            <template x-for="item in comparable.filter((c) => selected.includes(c.id))" :key="item.id">
                                <td class="max-w-[240px] border-t border-placeholder px-4 py-4 align-top text-sm leading-relaxed text-ink" x-text="item.notes || '—'"></td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-subtle">{{ __('auto_insurance.results.bookmark_hint') }}</p>
    </section>
@endsection
