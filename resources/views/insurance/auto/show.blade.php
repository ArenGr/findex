@extends('layouts.app')

@section('title', __('auto_insurance.results.heading') . ' — Findex')

@php
    use Illuminate\Support\Str;

    $sortedQuotes = $autoInsuranceRequest->quotes
        ->sortBy(fn ($quote) => [
            $quote->is_declined ? 1 : 0,
            match ($sort) {
                'price_desc' => -($quote->premium_amount !== null ? (float) $quote->premium_amount : 0),
                'rating' => -(float) ($quote->organization->reviews_avg_rating ?? 0),
                default => $quote->premium_amount !== null ? (float) $quote->premium_amount : PHP_FLOAT_MAX,
            },
        ])
        ->values();

    $cheapestQuoteId = $sortedQuotes->where('is_declined', false)
        ->sortBy(fn ($quote) => (float) $quote->premium_amount)
        ->first()?->id;
    $quotedCount = $sortedQuotes->where('is_declined', false)->count();

    // What the sidebar's price overview is built from.
    $premiums = $sortedQuotes->where('is_declined', false)
        ->pluck('premium_amount')
        ->map(fn ($amount) => (float) $amount);
    $currency = $sortedQuotes->firstWhere('is_declined', false)?->premium_currency;
    $money = fn (float $amount) => number_format($amount).' '.$currency;

    // Up to three at once - see compare_max_reached.
    $maxCompare = 3;
    $termLabel = __('auto_insurance.request.contract_terms.' . $autoInsuranceRequest->contract_term_months);

    $cardClass = 'rounded-2xl border border-placeholder bg-white shadow-sm';
    $iconDisc = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary';
@endphp

@section('content')
    {{-- Auto insurance results. --}}
    <x-page-hero
        :title="$quotedCount > 0 ? __('auto_insurance.results.ready_heading') : __('auto_insurance.results.heading')"
        :subtitle="$quotedCount > 0 ? __('auto_insurance.results.ready_subtitle', ['count' => $quotedCount]) : null"
    >
        <x-slot:eyebrow>
            <x-hero-badge>
                <x-slot:icon>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </x-slot:icon>
                {{ __('auto_insurance.request.badge') }}
            </x-hero-badge>
        </x-slot:eyebrow>

        <ul class="flex flex-wrap items-center gap-x-8 gap-y-3">
            @foreach (['benefit_real', 'benefit_no_fees', 'benefit_save'] as $benefit)
                <li class="flex items-center gap-2 text-sm text-ink">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    {{ __('auto_insurance.results.' . $benefit) }}
                </li>
            @endforeach
        </ul>

        <x-slot:illustration>
            <img
                src="{{ asset('images/insurance/hero-car-ins.webp') }}?v={{ filemtime(public_path('images/insurance/hero-car-ins.webp')) }}"
                alt=""
                width="630"
                height="420"
            >
        </x-slot:illustration>
    </x-page-hero>

    <div class="site-container py-6">
        @if (session('status') === 'insurance-request-submitted')
            <div class="mb-6 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('auto_insurance.results.submitted', ['count' => $quotedCount]) }}
            </div>
        @endif
        @if (session('status') === 'interest-marked')
            <div class="mb-6 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('auto_insurance.results.interest_marked_status') }}
            </div>
        @endif

        <div class="{{ $cardClass }} flex flex-col gap-4 p-4 md:flex-row md:items-center md:gap-0">
            <div class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1 md:w-auto md:min-w-[14rem] md:flex-nowrap">
                <span class="{{ $iconDisc }}">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 11l1.5-4.5A2 2 0 018.4 5h7.2a2 2 0 011.9 1.5L19 11"/><path d="M5 11h14a2 2 0 012 2v3a1 1 0 01-1 1h-1a1 1 0 01-1-1v-1H7v1a1 1 0 01-1 1H5a1 1 0 01-1-1v-3a2 2 0 012-2z"/></svg>
                </span>
                <span class="font-heading text-[15px] font-semibold whitespace-nowrap text-ink">{{ __('auto_insurance.results.your_details') }}</span>
                <a href="{{ route('insurance.auto.request') }}" class="flex items-center gap-1 text-[13px] font-medium whitespace-nowrap text-primary transition hover:text-primary-dark">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                    {{ __('auto_insurance.results.edit_details') }}
                </a>
            </div>

            <dl class="grid flex-1 grid-cols-1 gap-4 border-placeholder sm:grid-cols-3 md:border-l md:pl-6">
                @foreach ([
                    ['auto_insurance.results.vehicle_label', $autoInsuranceRequest->vehicle_plate],
                    ['auto_insurance.results.term_label', $termLabel],
                    ['auto_insurance.results.coverage_type_label', __('auto_insurance.results.product_name')],
                ] as [$label, $value])
                    <div>
                        <dt class="text-[11px] font-semibold tracking-[0.08em] text-muted uppercase">{{ __($label) }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-ink">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- The offers, with the request page's own sidebar column beside them. --}}
        <section
            x-data="{
                compare: [],
                max: {{ $maxCompare }},
                toggle(id) {
                    const at = this.compare.indexOf(id);
                    if (at > -1) return this.compare.splice(at, 1);
                    if (this.compare.length >= this.max) return;
                    this.compare.push(id);
                },
                full(id) { return this.compare.length >= this.max && !this.compare.includes(id) },
            }"
            class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,1fr)_300px]"
        >
            <div class="min-w-0">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-heading text-lg font-semibold text-ink">
                        {{ __('auto_insurance.results.offers_heading', ['count' => $sortedQuotes->count()]) }}
                    </h2>

                    @if ($quotedCount > 1)
                        <form method="GET" class="flex items-center gap-2">
                            @foreach (request()->except(['sort', 'page']) as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <label for="sort" class="text-[13px] text-muted">{{ __('auto_insurance.results.sort_by') }}</label>
                            <select
                                id="sort"
                                name="sort"
                                onchange="this.form.submit()"
                                class="rounded-lg border border-border-muted bg-white py-1.5 pr-8 pl-3 text-[13px] font-medium text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                            >
                                @foreach (['price' => 'sort_price_asc', 'price_desc' => 'sort_price_desc', 'rating' => 'sort_rating'] as $value => $label)
                                    <option value="{{ $value }}" @selected($sort === $value)>{{ __('auto_insurance.results.' . $label) }}</option>
                                @endforeach
                            </select>
                            <noscript><button type="submit" class="btn btn-secondary px-3 py-1.5 text-[13px]">{{ __('auto_insurance.results.sort_by') }}</button></noscript>
                        </form>
                    @endif
                </div>

                <div class="flex flex-col gap-4">
                    @forelse ($sortedQuotes as $quote)
                        @php
                            $isBest = ! $quote->is_declined && $quote->id === $cheapestQuoteId;
                            $hasDetails = $quote->coverage_summary || $quote->notes || $quote->organization->has_contact_info;
                        @endphp

                        <article
                            x-data="{ open: false }"
                            @class([
                                'relative overflow-hidden transition',
                                $cardClass,
                                'ring-2 ring-primary' => $isBest,
                                'opacity-70' => $quote->is_declined,
                                'hover:shadow-md' => ! $quote->is_declined,
                            ])
                        >
                            @if ($isBest)
                                <span class="absolute top-0 left-0 inline-flex items-center gap-1 rounded-br-xl bg-primary px-3 py-1.5 text-[11px] font-bold tracking-wide text-white uppercase">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2 9.2 8.6 2 9.2l5.5 4.7L5.8 21 12 17.3 18.2 21l-1.7-7.1L22 9.2l-7.2-.6z"/></svg>
                                    {{ __('auto_insurance.results.best_price_badge') }}
                                </span>
                            @endif

                            <div @class(['grid gap-5 p-5 md:grid-cols-[9rem_minmax(0,1fr)_15rem]', 'pt-10' => $isBest])>
                                {{-- Insurer --}}
                                <div class="flex items-center gap-3 md:flex-col md:items-start md:gap-2">
                                    <span class="flex h-11 w-28 items-center justify-center overflow-hidden rounded-lg bg-white">
                                        @if ($quote->organization->logo)
                                            <img src="{{ $quote->organization->logo }}" alt="{{ $quote->organization->name }}" class="max-h-full max-w-full object-contain">
                                        @else
                                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-primary/10 font-heading text-sm font-bold text-primary">{{ Str::of($quote->organization->name)->substr(0, 2)->upper() }}</span>
                                        @endif
                                    </span>
                                    <span class="text-[13px] font-semibold text-ink">{{ $quote->organization->name }}</span>

                                    @if ($quote->organization->reviews_count > 0)
                                        <span class="flex items-center gap-1.5">
                                            <x-star-rating :rating="$quote->organization->reviews_avg_rating ?? 0" size="h-3.5 w-3.5" />
                                            <span class="text-[11px] text-muted">
                                                {{ number_format($quote->organization->reviews_avg_rating, 1) }}
                                                ({{ __('auto_insurance.results.reviews_count', ['count' => $quote->organization->reviews_count]) }})
                                            </span>
                                        </span>
                                    @else
                                        <span class="text-[11px] text-subtle">{{ __('auto_insurance.results.no_reviews') }}</span>
                                    @endif

                                    <a href="{{ route('organizations.show', ['locale' => app()->getLocale(), 'organization' => $quote->organization->slug]) }}" class="text-[12px] font-medium text-primary hover:underline">
                                        {{ __('auto_insurance.results.about_insurer') }}
                                    </a>
                                </div>

                                {{-- What the policy is --}}
                                <div class="min-w-0">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="rounded-md bg-placeholder/40 px-2 py-0.5 text-[11px] font-semibold text-muted">{{ __('auto_insurance.results.product_name') }}</span>
                                        <span class="rounded-md bg-placeholder/40 px-2 py-0.5 text-[11px] font-semibold text-muted">{{ $termLabel }}</span>
                                    </div>

                                    <h3 class="mt-2 font-heading text-base font-semibold text-ink">{{ __('auto_insurance.results.product_name') }}</h3>

                                    @if ($quote->is_declined)
                                        <p class="mt-2 text-[13px] text-subtle">{{ __('auto_insurance.results.declined_hint') }}</p>
                                    @elseif ($hasDetails)
                                        {{-- Behind a toggle, and only ever the insurer's own words. --}}
                                        <button type="button" @click="open = !open" class="mt-3 flex items-center gap-1 text-[13px] font-medium text-primary hover:underline">
                                            <span x-text="open ? @js(__('auto_insurance.results.hide_details')) : @js(__('auto_insurance.results.view_details'))">{{ __('auto_insurance.results.view_details') }}</span>
                                            <svg class="h-3.5 w-3.5 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                        </button>

                                        <div x-show="open" x-cloak x-transition.opacity class="mt-3 space-y-3 border-t border-placeholder pt-3">
                                            @if ($quote->coverage_summary)
                                                <div>
                                                    <div class="text-[11px] font-semibold tracking-[0.08em] text-muted uppercase">{{ __('auto_insurance.results.coverage_summary_label') }}</div>
                                                    <p class="mt-1 text-[13px] leading-5 text-ink">{{ $quote->coverage_summary }}</p>
                                                </div>
                                            @endif
                                            @if ($quote->notes)
                                                <div>
                                                    <div class="text-[11px] font-semibold tracking-[0.08em] text-muted uppercase">{{ __('auto_insurance.results.notes_label') }}</div>
                                                    <p class="mt-1 text-[13px] leading-5 text-ink">{{ $quote->notes }}</p>
                                                </div>
                                            @endif
                                            @include('insurance.auto._quote-contacts', ['organization' => $quote->organization])
                                        </div>
                                    @endif
                                </div>

                                {{-- Price and what to do about it --}}
                                <div class="flex flex-col items-start gap-2 border-placeholder md:items-end md:border-l md:pl-5">
                                    @if ($quote->is_declined)
                                        <span class="rounded-full bg-placeholder/40 px-3 py-1 text-[11px] font-semibold text-subtle">{{ __('auto_insurance.results.declined_label') }}</span>
                                    @else
                                        <div class="md:text-right">
                                            <div class="font-heading text-3xl leading-none font-bold text-ink">{{ number_format((float) $quote->premium_amount) }}</div>
                                            <div class="mt-1 text-[13px] font-medium text-muted">{{ $quote->premium_currency }}</div>
                                            <div class="mt-0.5 text-[11px] text-subtle">{{ __('auto_insurance.results.total_for', ['term' => $termLabel]) }}</div>
                                        </div>

                                        @if ($quote->organization->website)
                                            <a
                                                href="{{ $quote->organization->website }}"
                                                target="_blank"
                                                rel="noopener nofollow"
                                                @class([
                                                    'mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-4 py-2.5 text-center text-[13px] font-semibold transition',
                                                    'bg-primary text-white shadow-sm hover:bg-primary-dark' => $isBest,
                                                    'border border-border-muted text-ink hover:bg-placeholder/30' => ! $isBest,
                                                ])
                                            >
                                                {{ __('auto_insurance.results.visit_site', ['insurer' => $quote->organization->name]) }}
                                                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                            </a>
                                        @endif

                                        @if ($quote->is_interested)
                                            <p class="flex items-center gap-1.5 text-[12px] font-semibold whitespace-nowrap text-primary" title="{{ __('auto_insurance.results.interested_confirmation') }}">
                                                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                                {{ __('auto_insurance.results.interested_badge') }}
                                            </p>
                                        @else
                                            <form method="POST" action="{{ URL::signedRoute('insurance.auto.quotes.interested', [
                                                'locale' => app()->getLocale(),
                                                'autoInsuranceRequest' => $autoInsuranceRequest->id,
                                                'quote' => $quote->id,
                                            ]) }}">
                                                @csrf
                                                <button type="submit" class="flex items-center gap-1.5 text-[12px] font-medium text-muted transition hover:text-primary">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                                                    {{ __('auto_insurance.results.interested_button') }}
                                                </button>
                                            </form>
                                        @endif

                                        @if ($quotedCount > 1)
                                            <label class="mt-1 flex cursor-pointer items-center gap-2 text-[12px] text-muted" :class="full(@js($quote->id)) && 'cursor-not-allowed opacity-40'">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-border-muted text-primary focus:ring-primary"
                                                    :checked="compare.includes(@js($quote->id))"
                                                    :disabled="full(@js($quote->id))"
                                                    @change="toggle(@js($quote->id))"
                                                >
                                                {{ __('auto_insurance.results.add_to_compare') }}
                                            </label>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="{{ $cardClass }} mx-auto flex max-w-xl flex-col items-center gap-4 p-10 text-center">
                            <span class="flex h-20 w-20 items-center justify-center rounded-full bg-primary/5 text-muted">
                                <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                            </span>
                            <h2 class="font-heading text-lg font-semibold text-ink">{{ __('auto_insurance.results.empty_heading') }}</h2>
                            <p class="text-sm text-muted">{{ __('auto_insurance.results.empty_body') }}</p>
                            <a href="{{ route('insurance.auto.request') }}" class="btn btn-primary mt-2">{{ __('auto_insurance.results.empty_retry') }}</a>
                        </div>
                    @endforelse
                </div>

                @if ($quotedCount > 0)
                    <p class="mt-6 text-center text-xs text-subtle">{{ __('auto_insurance.results.bookmark_hint') }}</p>
                @endif
            </div>

            {{-- Context sidebar, built like the request page's. --}}
            <aside class="space-y-4">
                @if ($premiums->count() > 1)
                    <div class="{{ $cardClass }} p-5">
                        <h3 class="flex items-center gap-2 font-heading text-[15px] font-semibold text-ink">
                            <svg class="h-[18px] w-[18px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="6" y1="20" x2="6" y2="13"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="4"/></svg>
                            {{ __('auto_insurance.results.price_overview') }}
                        </h3>

                        <dl class="mt-4 space-y-2.5 text-[13px]">
                            @foreach ([
                                ['price_lowest', $premiums->min()],
                                ['price_average', $premiums->avg()],
                                ['price_highest', $premiums->max()],
                            ] as [$label, $amount])
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="text-muted">{{ __('auto_insurance.results.' . $label) }}</dt>
                                    <dd class="font-semibold text-ink">{{ $money((float) $amount) }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if ($premiums->max() > $premiums->min())
                            <p class="mt-4 rounded-xl bg-primary/5 px-4 py-3 text-[13px] leading-5 text-ink">
                                {!! __('auto_insurance.results.price_saving', [
                                    'amount' => '<span class="font-heading text-base font-bold text-primary">' . e($money((float) $premiums->max() - (float) $premiums->min())) . '</span>',
                                ]) !!}
                            </p>
                        @endif
                    </div>
                @endif

                {{-- What the product is, not what any one insurer offers. --}}
                <div class="{{ $cardClass }} p-5">
                    <h3 class="flex items-center gap-2 font-heading text-[15px] font-semibold text-ink">
                        <svg class="h-[18px] w-[18px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                        {{ __('auto_insurance.results.coverage_includes') }}
                    </h3>
                    <ul class="mt-4 space-y-2.5">
                        @foreach (['coverage_item_liability', 'coverage_item_property', 'coverage_item_injury', 'coverage_item_area'] as $item)
                            <li class="flex gap-2.5 text-[13px] leading-5 text-ink">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ __('auto_insurance.results.' . $item) }}
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-4 border-t border-placeholder pt-3 text-[12px] leading-5 text-muted">{{ __('auto_insurance.results.coverage_excluded') }}</p>
                    <p class="mt-2 text-[12px] leading-5 text-muted">{{ __('auto_insurance.results.coverage_note') }}</p>
                </div>

                <div class="{{ $cardClass }} p-5">
                    <h3 class="flex items-center gap-2 font-heading text-[15px] font-semibold text-ink">
                        <svg class="h-[18px] w-[18px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.1 14a5 5 0 1 0-6.2 0c.5.4.8 1 .9 1.6h4.4c.1-.6.4-1.2.9-1.6z"/></svg>
                        {{ __('auto_insurance.results.tips_title') }}
                    </h3>
                    <div class="mt-5 space-y-4">
                        @foreach ([
                            ['tip_mandatory', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
                            ['tip_compare', '<line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
                            ['tip_network', '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
                            ['tip_online', '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 1.8"/>'],
                        ] as [$tip, $icon])
                            <div class="flex gap-3">
                                <span class="{{ $iconDisc }}">
                                    <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                                </span>
                                <p class="text-[13px] leading-5 text-muted">{{ __('auto_insurance.results.' . $tip) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- The insurers themselves. --}}
                <div class="{{ $cardClass }} p-5">
                    <h3 class="font-heading text-[15px] font-semibold text-ink">{{ __('auto_insurance.results.companies_title') }}</h3>
                    <p class="mt-2 text-[13px] leading-5 text-muted">{{ __('auto_insurance.results.companies_body') }}</p>
                    <a href="{{ route('insurance.companies') }}" class="btn btn-secondary mt-4 w-full">
                        {{ __('auto_insurance.results.companies_cta') }}
                        <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>

            </aside>

            @if ($quotedCount > 1)
                {{-- Side by side, built from the cards already on the page - no request, no route. --}}
                <div
                    x-show="compare.length > 1"
                    x-cloak
                    x-transition.opacity
                    class="lg:col-span-2"
                >
                    <div class="{{ $cardClass }} overflow-x-auto">
                        <h2 class="border-b border-placeholder px-5 py-4 font-heading text-lg font-semibold text-ink">
                            {{ __('auto_insurance.results.compare_heading') }}
                        </h2>
                        <table class="w-full text-left text-[13px]">
                            <thead>
                                <tr class="border-b border-placeholder">
                                    <th scope="col" class="px-5 py-3 font-semibold text-muted"></th>
                                    @foreach ($sortedQuotes->where('is_declined', false) as $quote)
                                        <th scope="col" x-show="compare.includes(@js($quote->id))" class="px-5 py-3 font-heading font-semibold text-ink">{{ $quote->organization->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-placeholder">
                                    <th scope="row" class="px-5 py-3 font-semibold text-muted">{{ __('auto_insurance.results.compare_row_premium') }}</th>
                                    @foreach ($sortedQuotes->where('is_declined', false) as $quote)
                                        <td x-show="compare.includes(@js($quote->id))" class="px-5 py-3 font-heading text-base font-bold text-ink">{{ $money((float) $quote->premium_amount) }}</td>
                                    @endforeach
                                </tr>
                                <tr class="border-b border-placeholder">
                                    <th scope="row" class="px-5 py-3 font-semibold text-muted">{{ __('auto_insurance.results.compare_row_coverage') }}</th>
                                    @foreach ($sortedQuotes->where('is_declined', false) as $quote)
                                        <td x-show="compare.includes(@js($quote->id))" class="px-5 py-3 text-ink">{{ $quote->coverage_summary ?: '—' }}</td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th scope="row" class="px-5 py-3 font-semibold text-muted">{{ __('auto_insurance.results.compare_row_notes') }}</th>
                                    @foreach ($sortedQuotes->where('is_declined', false) as $quote)
                                        <td x-show="compare.includes(@js($quote->id))" class="px-5 py-3 text-ink">{{ $quote->notes ?: '—' }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    x-show="compare.length > 0"
                    x-cloak
                    x-transition
                    class="fixed inset-x-0 bottom-0 z-40 border-t border-placeholder bg-white/95 px-4 py-3 shadow-[0_-4px_20px_rgba(15,23,42,0.08)] backdrop-blur"
                >
                    <div class="site-container flex items-center justify-between gap-4">
                        <p class="text-[13px] text-muted">
                            <span class="font-semibold text-ink" x-text="compare.length"></span>
                            {{ __('auto_insurance.results.quotes_selected') }}
                            <span x-show="compare.length >= max" class="ml-1 text-[12px] text-subtle">{{ __('auto_insurance.results.compare_max_reached') }}</span>
                        </p>
                        <button type="button" @click="compare = []" class="text-[13px] font-medium text-muted hover:text-ink">
                            {{ __('auto_insurance.results.compare_bar_clear') }}
                        </button>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection
