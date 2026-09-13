@props([
    'market' => null,
    'scrapedAt' => null,
    'stale' => false,
    'distance' => null,
    'directions' => null,
    'changedAt' => null,
    'timestampClass' => '',
])

<span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs break-words text-muted">
    @if ($market)
        <span>{{ $market }}</span>
    @endif

    @if ($scrapedAt)
        <span @class(['flex items-center gap-1.5', $timestampClass])>
            @if ($market)
                <span aria-hidden="true">&middot;</span>
            @endif
            <x-rates.freshness :scraped-at="$scrapedAt" :stale="$stale" :changed-at="$changedAt" />
        </span>
    @endif

    @if ($distance)
        <span aria-hidden="true">&middot;</span>
        <span>{{ $distance }}</span>
    @endif

    @if ($directions)
        <span aria-hidden="true">&middot;</span>
        {{-- Nobody comes here to read a rate; they come to go and exchange money. --}}
        <a
            href="{{ $directions['url'] }}"
            target="_blank"
            rel="noopener noreferrer"
            class="-my-2.5 inline-flex items-center gap-1 py-2.5 underline hover:text-ink"
            title="{{ $directions['address'] ?: $directions['name'] }}"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 shrink-0" aria-hidden="true">
                <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                <circle cx="12" cy="10" r="3" />
            </svg>
            {{ __('rates.directions') }}
        </a>
    @endif
</span>
