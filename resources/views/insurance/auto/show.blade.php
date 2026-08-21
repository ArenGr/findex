@extends('layouts.app')

@section('title', __('auto_insurance.results.heading') . ' — Findex')

@php
    use Illuminate\Support\Str;

    $sortedQuotes = $autoInsuranceRequest->quotes
        ->sortBy(fn ($quote) => [
            $quote->is_declined ? 1 : 0,
            $quote->premium_amount !== null ? (float) $quote->premium_amount : PHP_FLOAT_MAX,
        ])
        ->values();

    $cheapestQuoteId = $sortedQuotes->firstWhere('is_declined', false)?->id;
    $quotedCount = $sortedQuotes->where('is_declined', false)->count();
    $termLabel = __('auto_insurance.request.contract_terms.' . $autoInsuranceRequest->contract_term_months);
@endphp

@section('content')
    {{-- Auto insurance results, from the approved Stitch results screen -
         rebuilt with Findex tokens and inline icons, inside the app's own
         header and footer. Shows only what our data supports: this is
         compulsory motor TPL, one standardised product, so each card carries
         the insurer, the premium and a link to them - no invented policy
         features, no "select & buy" flow we do not have. --}}
    <section class="mx-auto max-w-5xl px-6 py-12 lg:px-10 lg:py-16">
        @if (session('status') === 'insurance-request-submitted')
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('auto_insurance.results.submitted', ['count' => $quotedCount]) }}
            </div>
        @endif
        @if (session('status') === 'interest-marked')
            <div class="mb-8 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('auto_insurance.results.interest_marked_status') }}
            </div>
        @endif

        {{-- Progress trail (decorative: the request is one page, this marks
             where the journey lands). --}}
        <div class="mb-10 flex items-center justify-center gap-3 text-sm text-muted">
            @foreach (['trail_vehicle' => true, 'trail_details' => true, 'trail_compare' => false] as $key => $done)
                @if (! $loop->first)
                    <span class="h-px w-8 bg-placeholder"></span>
                @endif
                <span @class(['flex items-center gap-1.5', 'text-primary' => $done, 'font-semibold text-ink' => ! $done])>
                    @if ($done)
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.2 14.5-3.5-3.5 1.4-1.4 2.1 2.1 4.6-4.6 1.4 1.4-6 6z"/></svg>
                    @else
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5" fill="currentColor" stroke="none"/></svg>
                    @endif
                    {{ __('auto_insurance.results.' . $key) }}
                </span>
            @endforeach
        </div>

        @if ($quotedCount > 0)
            {{-- Header --}}
            <div class="mx-auto flex max-w-2xl flex-col items-center gap-4 text-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary shadow-sm">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </span>
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-4xl">{{ __('auto_insurance.results.ready_heading') }}</h1>
                <p class="text-base text-muted lg:text-lg">{{ __('auto_insurance.results.ready_subtitle', ['count' => $quotedCount]) }}</p>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-semibold text-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    {{ __('auto_insurance.results.quotes_received', ['count' => $quotedCount]) }}
                </span>
            </div>
        @else
            <h1 class="text-center font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('auto_insurance.results.heading') }}</h1>
        @endif

        {{-- Request summary bar --}}
        <div class="mt-8 flex flex-col items-center justify-between gap-4 rounded-2xl border border-placeholder bg-white p-4 shadow-sm md:flex-row">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-base text-ink">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-subtle" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 11l1.5-4.5A2 2 0 018.4 5h7.2a2 2 0 011.9 1.5L19 11m-14 0h14m-14 0a2 2 0 00-2 2v3a1 1 0 001 1h1a1 1 0 001-1v-1h10v1a1 1 0 001 1h1a1 1 0 001-1v-3a2 2 0 00-2-2"/></svg>
                    <span class="font-semibold">{{ $autoInsuranceRequest->vehicle_plate }}</span>
                </span>
                <span class="hidden h-4 w-px bg-placeholder md:block"></span>
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    {{ $termLabel }}
                </span>
            </div>
            <a href="{{ route('insurance.auto.request') }}" class="flex items-center gap-1.5 text-sm font-medium text-primary transition hover:text-primary-dark">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                {{ __('auto_insurance.results.edit_details') }}
            </a>
        </div>

        {{-- Toolbar --}}
        <div class="mt-8 flex items-center justify-between border-b border-placeholder pb-2">
            <h2 class="font-heading text-lg font-semibold text-ink">{{ __('auto_insurance.results.offers_heading', ['count' => $sortedQuotes->count()]) }}</h2>
            @if ($quotedCount > 1)
                <span class="text-sm text-muted">{{ __('auto_insurance.results.sorted_by_price') }}</span>
            @endif
        </div>

        {{-- Quote cards --}}
        <div class="mt-4 flex flex-col gap-4">
            @forelse ($sortedQuotes as $quote)
                @php $isBest = ! $quote->is_declined && $quote->id === $cheapestQuoteId; @endphp

                <div @class([
                    'relative overflow-hidden rounded-3xl bg-white p-6 shadow-sm transition hover:shadow-md',
                    'border-2 border-primary' => $isBest,
                    'border border-placeholder' => ! $isBest,
                    'opacity-70' => $quote->is_declined,
                ])>
                    @if ($isBest)
                        <span class="absolute right-0 top-0 rounded-bl-lg bg-primary px-4 py-1 text-xs font-semibold uppercase tracking-wider text-white">
                            {{ __('auto_insurance.results.best_price_badge') }}
                        </span>
                    @endif

                    <div class="flex flex-col items-center justify-between gap-6 md:flex-row md:items-start">
                        {{-- Insurer --}}
                        <div class="flex w-full flex-col items-center gap-3 md:w-1/4 md:items-start">
                            <div class="flex h-12 w-24 items-center justify-center overflow-hidden rounded-lg border border-placeholder bg-white">
                                @if ($quote->organization->logo)
                                    <img src="{{ $quote->organization->logo }}" alt="{{ $quote->organization->name }}" class="max-h-full max-w-full object-contain">
                                @else
                                    <span class="font-heading text-sm font-bold text-primary">{{ Str::of($quote->organization->name)->substr(0, 2)->upper() }}</span>
                                @endif
                            </div>
                            <span class="text-center text-sm font-semibold text-ink md:text-left">{{ $quote->organization->name }}</span>
                        </div>

                        {{-- Details --}}
                        <div class="flex w-full flex-col gap-2 md:w-2/4">
                            <span class="w-fit rounded bg-placeholder/40 px-2 py-1 text-xs font-medium text-muted">{{ $termLabel }}</span>
                            <h3 class="font-heading text-lg font-semibold text-ink">{{ __('auto_insurance.results.product_name') }}</h3>
                            @if ($quote->coverage_summary)
                                <p class="text-sm text-muted">{{ $quote->coverage_summary }}</p>
                            @endif
                            @if ($quote->notes)
                                <p class="mt-1 rounded-lg bg-primary/5 px-3 py-2 text-sm leading-relaxed text-ink">{{ $quote->notes }}</p>
                            @endif
                        </div>

                        {{-- Price & action --}}
                        <div class="flex w-full flex-col items-center gap-3 border-t border-placeholder pt-4 md:mt-0 md:w-1/4 md:items-end md:border-l md:border-t-0 md:pl-6 md:pt-0">
                            @if ($quote->is_declined)
                                <span class="rounded-full bg-placeholder/40 px-3 py-1 text-xs font-semibold text-subtle">{{ __('auto_insurance.results.declined_label') }}</span>
                                <p class="text-center text-xs text-subtle md:text-right">{{ __('auto_insurance.results.declined_hint') }}</p>
                            @else
                                <div class="text-center md:text-right">
                                    <div class="font-heading text-3xl font-bold leading-none text-ink">{{ rtrim(rtrim((string) $quote->premium_amount, '0'), '.') }}</div>
                                    <div class="mt-1 text-sm font-medium text-muted">{{ $quote->premium_currency }}</div>
                                    <div class="mt-1 text-xs text-subtle">{{ __('auto_insurance.results.total_for', ['term' => $termLabel]) }}</div>
                                </div>

                                @if ($quote->organization->website)
                                    <a href="{{ $quote->organization->website }}" target="_blank" rel="noopener nofollow"
                                       @class([
                                           'w-full rounded-xl px-6 py-3 text-center text-sm font-medium transition',
                                           'bg-primary text-white shadow-sm hover:bg-primary-dark' => $isBest,
                                           'border border-border-muted text-ink hover:bg-placeholder/30' => ! $isBest,
                                       ])>
                                        {{ __('auto_insurance.results.visit_site', ['insurer' => $quote->organization->name]) }}
                                    </a>
                                @endif

                                @if ($quote->is_interested)
                                    <p class="text-xs font-medium text-primary">✓ {{ __('auto_insurance.results.interested_confirmation') }}</p>
                                @else
                                    <form method="POST" action="{{ URL::signedRoute('insurance.auto.quotes.interested', [
                                        'locale' => app()->getLocale(),
                                        'autoInsuranceRequest' => $autoInsuranceRequest->id,
                                        'quote' => $quote->id,
                                    ]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-primary hover:underline">{{ __('auto_insurance.results.interested_button') }}</button>
                                    </form>
                                @endif

                                @if ($quote->organization->has_contact_info)
                                    <div class="flex flex-wrap justify-center gap-2 md:justify-end">
                                        @if ($quote->organization->contact_phone)
                                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $quote->organization->contact_phone) }}" class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/40 text-ink transition hover:bg-placeholder/60" aria-label="{{ __('organizations.contact_call') }}">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                            </a>
                                        @endif
                                        @if ($quote->organization->contact_whatsapp)
                                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $quote->organization->contact_whatsapp) }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/40 text-ink transition hover:bg-placeholder/60" aria-label="{{ __('organizations.contact_whatsapp') }}">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2zm5.6 14.2c-.2.6-1.2 1.2-1.7 1.2-.4.1-1 .1-1.6-.1-.4-.1-.9-.3-1.5-.5-2.6-1.1-4.3-3.8-4.4-4-.1-.2-1-1.4-1-2.6s.6-1.8.9-2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.3 0 .5l-.3.5c-.2.2-.3.4-.1.7.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.2.1.4.1.5-.1l.7-.9c.2-.2.4-.2.6-.1l1.8.9c.2.1.4.2.5.3.1.2.1.7-.1 1.3z"/></svg>
                                            </a>
                                        @endif
                                        @if ($quote->organization->contact_telegram)
                                            <a href="https://t.me/{{ ltrim($quote->organization->contact_telegram, '@') }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/40 text-ink transition hover:bg-placeholder/60" aria-label="{{ __('organizations.contact_telegram') }}">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3 18.6 20c-.2 1-.9 1.3-1.7.8l-4.6-3.4-2.2 2.1c-.2.2-.5.5-.9.5l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L7.2 13 2.7 11.6c-1-.3-1-.9.2-1.4l17-6.5c.8-.3 1.5.2 1.2 1z"/></svg>
                                            </a>
                                        @endif
                                        @if ($quote->organization->contact_instagram)
                                            <a href="https://instagram.com/{{ ltrim($quote->organization->contact_instagram, '@') }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/40 text-ink transition hover:bg-placeholder/60" aria-label="{{ __('organizations.contact_instagram') }}">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><line x1="17.5" y1="6.5" x2="17.5" y2="6.5"/></svg>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                {{-- Empty state, from the approved Stitch "no quotes" screen. --}}
                <div class="mx-auto flex max-w-xl flex-col items-center gap-4 rounded-3xl border border-placeholder p-10 text-center shadow-sm">
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-primary/5 text-muted">
                        <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                    </span>
                    <h2 class="font-heading text-lg font-semibold text-ink">{{ __('auto_insurance.results.empty_heading') }}</h2>
                    <p class="text-sm text-muted">{{ __('auto_insurance.results.empty_body') }}</p>
                    <a href="{{ route('insurance.auto.request') }}" class="mt-2 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-medium text-white transition hover:bg-primary-dark">
                        {{ __('auto_insurance.results.empty_retry') }}
                    </a>
                </div>
            @endforelse
        </div>

        @if ($quotedCount > 0)
            <p class="mt-8 text-center text-xs text-subtle">{{ __('auto_insurance.results.bookmark_hint') }}</p>
        @endif
    </section>
@endsection
