@extends('layouts.app')

@section('title', $metaTitle)
@section('description', $metaDescription)

@php
    use Illuminate\Support\Str;

    $cardClass = 'rounded-2xl border border-placeholder bg-white shadow-sm';
    $iconDisc = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary';
@endphp

@section('content')
    {{-- The insurance directory. --}}
    <x-page-hero :title="$heading" :subtitle="$subtitle">
        <x-slot:eyebrow>
            <nav aria-label="{{ __('organizations.breadcrumb') }}" class="flex items-center gap-2 text-xs text-muted">
                <a href="{{ route('home') }}" class="hover:text-primary">{{ __('nav.home') }}</a>
                <span aria-hidden="true">›</span>
                <span class="font-medium text-ink">{{ __('nav.insurance.label') }}</span>
            </nav>
        </x-slot:eyebrow>

        {{-- One counted fact and two standing ones. --}}
        <ul class="flex flex-wrap items-center gap-x-10 gap-y-4">
            <li class="flex items-center gap-3">
                <span class="{{ $iconDisc }}">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                </span>
                <span>
                    <span class="block font-heading text-lg font-bold text-ink">{{ $organizations->total() }}</span>
                    <span class="block text-xs text-muted">{{ $statLabel }}</span>
                </span>
            </li>
            @foreach ([
                ['stat_verified', 'stat_verified_sub', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>'],
                ['stat_reviews', 'stat_reviews_sub', '<path d="M12 2l2.9 6.2 6.6.9-4.8 4.7 1.2 6.7L12 17.3 6.1 20.5l1.2-6.7-4.8-4.7 6.6-.9z"/>'],
            ] as [$title, $sub, $icon])
                <li class="flex items-center gap-3">
                    <span class="{{ $iconDisc }}">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                    </span>
                    <span>
                        <span class="block text-sm font-bold text-ink">{{ __('auto_insurance.companies.' . $title) }}</span>
                        <span class="block text-xs text-muted">{{ __('auto_insurance.companies.' . $sub) }}</span>
                    </span>
                </li>
            @endforeach
        </ul>

        <x-slot:illustration>
            {{-- The car the request form and the results both carry. --}}
            <img
                src="{{ asset('images/insurance/hero-car-ins.webp') }}?v={{ filemtime(public_path('images/insurance/hero-car-ins.webp')) }}"
                alt=""
                width="630"
                height="420"
            >
        </x-slot:illustration>
    </x-page-hero>

    <div class="site-container">
        <section class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <div class="min-w-0">
                {{-- Search and order, in one GET so they compose: searching keeps the chosen order and vice versa. --}}
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <div class="relative order-1 w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-subtle">
                            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="{{ __('organizations.search_placeholder') }}"
                            class="h-11 w-full rounded-lg border border-border-muted bg-white pr-4 pl-11 text-sm text-ink outline-none transition placeholder:text-subtle/70 hover:border-primary/60 focus:border-primary focus:ring-2 focus:ring-primary/15"
                        >
                    </div>
                    <button type="submit" class="btn btn-primary order-2 h-11 shrink-0 sm:order-none">{{ __('organizations.search_button') }}</button>

                    <label for="sort" class="order-3 text-[13px] text-muted sm:order-none sm:ml-auto">{{ __('auto_insurance.companies.sort_by') }}</label>
                    <select
                        id="sort"
                        name="sort"
                        onchange="this.form.submit()"
                        class="order-4 h-11 min-w-0 flex-1 rounded-lg border border-border-muted bg-white py-1.5 pr-8 pl-3 text-[13px] font-medium text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/15 sm:order-none sm:flex-none"
                    >
                        @foreach (['rated' => 'sort_rated', 'reviewed' => 'sort_reviewed', 'name' => 'sort_name'] as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ __('auto_insurance.companies.' . $label) }}</option>
                        @endforeach
                    </select>
                </form>

                @if ($showCompare && $organizations->isNotEmpty())
                    <div x-data class="mt-5 flex flex-wrap items-center justify-between gap-3 border-b border-placeholder pb-3">
                        <p class="text-[13px] text-muted">
                            {{ __('auto_insurance.companies.select_to_compare') }}
                            <span class="font-semibold text-ink">(<span x-text="$store.compare.items.length">0</span>/3)</span>
                        </p>
                        <a
                            href="{{ route('organizations.compare') }}"
                            x-show="$store.compare.items.length >= 2"
                            x-cloak
                            :href="'{{ route('organizations.compare') }}?orgs=' + $store.compare.items.map((item) => item.slug).join(',')"
                            class="flex items-center gap-1.5 text-[13px] font-semibold text-primary hover:underline"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="7" height="16" rx="1"/><rect x="14" y="4" width="7" height="10" rx="1"/></svg>
                            {{ __('organizations.compare_selected') }}
                        </a>
                    </div>
                @endif

                <div class="mt-4 flex flex-col gap-3">
                    @forelse ($organizations as $organization)
                        {{-- Stacked on a phone. --}}
                        <article class="{{ $cardClass }} flex flex-col gap-3 p-4 transition hover:shadow-md sm:flex-row sm:items-center sm:gap-4">
                            <a href="{{ route('organizations.show', $organization) }}" class="flex min-w-0 flex-1 items-center gap-4">
                                @if ($organization->logo)
                                    <img src="{{ $organization->logo }}" alt="" class="h-12 w-12 shrink-0 rounded-full object-contain">
                                @else
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 font-heading text-sm font-bold text-primary">
                                        {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                                    </span>
                                @endif

                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-ink">{{ $organization->name }}</span>
                                        <x-organization-badges :organization="$organization" />
                                    </span>

                                    @if ($organization->description)
                                        <span class="mt-1 block truncate text-[13px] text-muted">{{ $organization->description }}</span>
                                    @endif

                                    <span class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                                        <span class="flex items-center gap-1.5">
                                            <x-star-rating :rating="$organization->reviews_avg_rating ?? 0" size="h-3.5 w-3.5" />
                                            @if ($organization->reviews_count > 0)
                                                {{ number_format($organization->reviews_avg_rating, 1) }}
                                                ({{ trans_choice('organizations.reviews_count', $organization->reviews_count, ['count' => $organization->reviews_count]) }})
                                            @else
                                                {{ __('organizations.unrated') }}
                                            @endif
                                        </span>

                                        @if ($organization->branches_count > 0)
                                            <span class="flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                                {{ trans_choice('auto_insurance.companies.branches', $organization->branches_count, ['count' => $organization->branches_count]) }}
                                            </span>
                                        @endif
                                    </span>
                                </span>
                            </a>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                @if ($showCompare)
                                    <x-compare-toggle :organization="$organization" class="shrink-0" />
                                @endif
                                <a href="{{ route('insurance.auto.request') }}" class="btn btn-primary shrink-0 px-4 py-2 text-[13px]">
                                    {{ __('auto_insurance.companies.get_quote') }}
                                </a>
                                <a href="{{ route('organizations.show', $organization) }}" class="btn btn-secondary shrink-0 px-4 py-2 text-[13px]">
                                    {{ __('auto_insurance.companies.view_profile') }}
                                </a>
                            </div>
                        </article>
                    @empty
                        <p class="{{ $cardClass }} px-5 py-12 text-center text-sm text-muted">{{ __('organizations.no_organizations') }}</p>
                    @endforelse
                </div>

                <div class="mt-8">{{ $organizations->links() }}</div>
            </div>

            <aside class="space-y-4">
                <div class="{{ $cardClass }} p-5">
                    <h2 class="flex items-center gap-2 font-heading text-[15px] font-semibold text-ink">
                        <svg class="h-[18px] w-[18px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.1 14a5 5 0 1 0-6.2 0c.5.4.8 1 .9 1.6h4.4c.1-.6.4-1.2.9-1.6z"/></svg>
                        {{ __('auto_insurance.companies.helps_title') }}
                    </h2>
                    <ul class="mt-4 space-y-2.5">
                        @foreach (['helps_1', 'helps_2', 'helps_3', 'helps_4', 'helps_5'] as $item)
                            <li class="flex gap-2.5 text-[13px] leading-5 text-ink">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ __('auto_insurance.companies.' . $item) }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-2xl border border-primary/20 bg-primary/5 p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="{{ $iconDisc }}">
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 11l1.5-4.5A2 2 0 018.4 5h7.2a2 2 0 011.9 1.5L19 11"/><path d="M5 11h14a2 2 0 012 2v3a1 1 0 01-1 1h-1a1 1 0 01-1-1v-1H7v1a1 1 0 01-1 1H5a1 1 0 01-1-1v-3a2 2 0 012-2z"/></svg>
                        </span>
                        <div>
                            <h2 class="font-heading text-[15px] font-semibold text-ink">{{ __('auto_insurance.companies.quote_card_title') }}</h2>
                            <p class="mt-2 text-[13px] leading-5 text-muted">{{ __('auto_insurance.companies.quote_card_body') }}</p>
                        </div>
                    </div>
                    <a href="{{ $ctaRoute }}" class="btn btn-primary mt-4 w-full">
                        {{ $ctaLabel }}
                        <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </aside>
        </section>
    </div>
@endsection
