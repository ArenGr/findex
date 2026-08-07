@php
    // whereHas(), not having('reviews_count', '>', 0) - reviews_count is a
    // withCount() subquery-select alias, not a real GROUP BY aggregate,
    // and MySQL's relaxed SQL mode is the only reason HAVING against it
    // ever worked; SQLite (the test suite's driver) rejects it outright
    // with "HAVING clause on a non-aggregate query". whereHas() compiles
    // to a portable WHERE EXISTS and needs no such leniency.
    $topRated = \App\Models\Organization::active()
        ->withRatingStats()
        ->whereHas('reviews')
        ->orderByDesc('reviews_avg_rating')
        ->orderByDesc('reviews_count')
        ->take(4)
        ->get();
@endphp

@if ($topRated->isNotEmpty())
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('organizations.top_rated_heading') }}</h2>
        <p class="mt-2 max-w-2xl text-sm text-muted">
            {{ __('organizations.top_rated_subtitle') }}
        </p>

        {{--
            A ranked list rather than a card grid - the rank badge (gold for
            #1, same convention as components/rates-table.blade.php's
            leaderboard rows) is what carries the "top rated" meaning here,
            and a row layout reads that ranking at a glance without needing
            a separate mobile/desktop treatment.
        --}}
        <div class="mt-10 divide-y divide-placeholder overflow-hidden rounded-2xl border border-placeholder bg-white shadow-sm">
            @foreach ($topRated as $i => $organization)
                <a
                    href="{{ route('organizations.show', $organization) }}"
                    class="group flex items-center gap-4 px-5 py-4 transition duration-300 hover:bg-placeholder/10 sm:px-6 sm:py-5"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $i === 0 ? 'bg-accent-yellow text-ink' : 'bg-placeholder/60 text-muted' }}"
                    >
                        {{ $i + 1 }}
                    </span>

                    @if ($organization->logo)
                        <img src="{{ $organization->logo }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-contain">
                    @else
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                            {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink">{{ $organization->name }}</p>
                        <div class="mt-0.5 flex items-center gap-1.5">
                            <x-star-rating :rating="$organization->reviews_avg_rating" />
                            <span class="text-xs text-muted">
                                {{ number_format($organization->reviews_avg_rating, 1) }}
                                ({{ trans_choice('organizations.reviews_count', $organization->reviews_count, ['count' => $organization->reviews_count]) }})
                            </span>
                        </div>
                    </div>

                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 shrink-0 fill-none stroke-current text-muted transition duration-300 group-hover:translate-x-1 group-hover:text-primary">
                        <path d="M9 6l6 6-6 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            @endforeach
        </div>

        <div class="mt-10 text-center">
            <a href="{{ route('organizations.index') }}" class="inline-block bg-primary px-8 py-3 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md">
                {{ __('organizations.view_all') }}
            </a>
        </div>
    </section>
@endif
