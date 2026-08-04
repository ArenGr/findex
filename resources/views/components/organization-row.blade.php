@props(['organization', 'showCompare' => false])

{{--
    Shared by the generic directory (organizations/index.blade.php) and the
    dedicated /banks and /travel-agencies landing pages - one row style kept
    in one place rather than copy-pasted three times. A list of rows (not a
    grid of cards) since these pages can run to dozens of organizations -
    a list stays scannable at that length, where a 3-column grid mostly
    just adds vertical scrolling without showing more at a glance.
--}}
<div class="flex flex-wrap items-center gap-4 px-5 py-4 transition duration-300 hover:bg-primary/5">
    <a href="{{ route('organizations.show', $organization) }}" class="flex min-w-0 flex-1 items-center gap-4">
        @if ($organization->logo)
            <img src="{{ $organization->logo }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-contain">
        @else
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                {{ Str::of($organization->name)->substr(0, 2)->upper() }}
            </div>
        @endif

        <div class="min-w-0 flex-1">
            <p class="truncate font-semibold text-ink">{{ $organization->name }}</p>
            <p class="text-xs text-subtle">{{ __('organizations.types.' . $organization->type) }}</p>
        </div>

        <div class="hidden shrink-0 items-center gap-2 sm:flex">
            <x-star-rating :rating="$organization->reviews_avg_rating ?? 0" />
            <span class="text-xs whitespace-nowrap text-muted">
                @if ($organization->reviews_count > 0)
                    {{ number_format($organization->reviews_avg_rating, 1) }}
                    ({{ trans_choice('organizations.reviews_count', $organization->reviews_count, ['count' => $organization->reviews_count]) }})
                @else
                    {{ __('organizations.unrated') }}
                @endif
            </span>
        </div>

        <div class="hidden shrink-0 md:block">
            <x-organization-badges :organization="$organization" />
        </div>
    </a>

    @if ($showCompare)
        <x-compare-toggle :organization="$organization" class="shrink-0" />
    @endif
</div>
