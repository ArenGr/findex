@php
    $topRated = \App\Models\Organization::active()
        ->withRatingStats()
        ->having('reviews_count', '>', 0)
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

        <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($topRated as $organization)
                <a href="{{ route('organizations.show', $organization) }}" class="block border border-placeholder p-5 transition hover:border-primary">
                    <div class="flex items-center gap-3">
                        @if ($organization->logo)
                            <img src="{{ $organization->logo }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-contain">
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                                {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                            </div>
                        @endif

                        <span class="min-w-0 flex-1 truncate font-semibold text-ink">{{ $organization->name }}</span>
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <x-star-rating :rating="$organization->reviews_avg_rating" />
                        <span class="text-xs text-muted">
                            {{ number_format($organization->reviews_avg_rating, 1) }}
                            ({{ trans_choice('organizations.reviews_count', $organization->reviews_count, ['count' => $organization->reviews_count]) }})
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-10 text-center">
            <a href="{{ route('organizations.index') }}" class="inline-block bg-primary px-8 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('organizations.view_all') }}
            </a>
        </div>
    </section>
@endif
