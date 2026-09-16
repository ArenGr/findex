@props([
    'rate',
    'best' => false,
    'badge' => null,
    'bestCount' => 1,
    'total' => null,
    'totalLabel' => null,
    'stale' => false,
    'showMarket' => false,
    'distance' => null,
])

{{--
    One rate, one card, at every width.

    The page used to render each row twice - a card below sm and a table row
    from sm - which is two markups to keep in step and the reason the phone and
    the desktop had drifted apart. This is the single result card from the
    design brief, and it is the shape insurance quotes and travel offers use
    too, so a visitor who has read one results page can read all of them.
--}}
<article @class([
    'relative rounded-2xl border bg-surface p-4 transition-colors sm:p-5',
    'border-primary/40' => $best,
    'border-border hover:border-primary/30' => ! $best,
])>
    @if ($best && $badge)
        {{-- The star carries the accessible label, which says how many
             organizations share this rate when more than one does. --}}
        <span class="absolute -top-2.5 right-4 flex items-center gap-1 rounded-full bg-accent-yellow px-2.5 py-0.5 text-[11px] font-bold text-ink">
            <x-rates.best-chip :count="$bestCount" />
            {{ $badge }}
        </span>
    @endif

    <div class="flex flex-wrap items-center gap-x-5 gap-y-4">
        {{-- Who --}}
        {{-- Full width on a phone: the figures do not shrink, so sharing a
             row with them squeezed the name to nothing. --}}
        <a @if ($rate->organization_url) href="{{ $rate->organization_url }}" @endif class="flex w-full min-w-0 items-center gap-3 sm:w-auto sm:flex-1 sm:basis-56">
            <x-rates.org-mark :logo="$rate->organization_logo" :name="$rate->organization_name" />

            <span class="min-w-0">
                <span class="block truncate font-semibold text-ink">{{ $rate->organization_name }}</span>
                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted">
                    @if ($showMarket)
                        <span>{{ __('rates.market_badge.' . $rate->organization_type) }}</span>
                    @endif
                    @if ($distance)
                        <span aria-hidden="true">&middot;</span>
                        <span>{{ $distance }}</span>
                    @endif
                    @if ($rate->organization_reviews_count > 0)
                        <span aria-hidden="true">&middot;</span>
                        <span class="flex items-center gap-1">
                            <x-lucide name="star" :size="12" class="text-accent-yellow" />
                            {{ number_format((float) $rate->organization_reviews_avg_rating, 1) }}
                        </span>
                    @endif
                </span>
            </span>
        </a>

        {{-- The numbers. Same three columns in the same order on every card. --}}
        <div class="flex w-full shrink-0 items-start justify-between gap-4 sm:w-auto sm:justify-start sm:gap-8">
            @foreach ([
                ['label' => __('rates.buy_column'), 'value' => $rate->buy_rate, 'tone' => 'text-primary'],
                ['label' => __('rates.sell_column'), 'value' => $rate->sell_rate, 'tone' => 'text-accent-red'],
                ['label' => __('rates.spread_column'), 'value' => $rate->spread, 'tone' => 'text-ink'],
            ] as $figure)
                <span class="block">
                    <span class="block text-[11px] font-semibold tracking-wider text-muted uppercase">{{ $figure['label'] }}</span>
                    <span class="mt-0.5 block text-lg font-bold whitespace-nowrap tabular-nums sm:text-xl {{ $figure['tone'] }}">
                        {{ number_format((float) $figure['value'], 2) }}
                    </span>
                </span>
            @endforeach

            @if ($total !== null)
                <span class="block">
                    <span class="block text-[11px] font-semibold tracking-wider text-muted uppercase">{{ $totalLabel }}</span>
                    <span class="mt-0.5 block text-lg font-bold whitespace-nowrap text-ink tabular-nums sm:text-xl">{{ $total }}</span>
                </span>
            @endif
        </div>
    </div>

    {{-- When, and where to go next. --}}
    <div class="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-border pt-3 text-xs">
        <span class="text-muted">
            @if ($rate->scraped_at)
                <x-rates.freshness :scraped-at="$rate->scraped_at" :stale="$stale" :changed-at="$rate->changed_at ?? null" />
            @endif
        </span>

        <span class="flex items-center gap-4">
            @if ($rate->branch ?? null)
                <a href="{{ $rate->branch['url'] }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 font-semibold text-primary hover:underline">
                    <x-lucide name="map-pin" :size="14" />
                    {{ __('rates.directions') }}
                </a>
            @endif

            <a @if ($rate->organization_url) href="{{ $rate->organization_url }}" @endif class="flex items-center gap-1.5 font-semibold text-primary hover:underline">
                {{ __('rates.view_details') }}
                <x-lucide name="arrow-right" :size="14" />
            </a>
        </span>
    </div>
</article>
