@props([
    'market' => null,
    'scrapedAt' => null,
    'stale' => false,
    'distance' => null,
    'directions' => null,
    'changedAt' => null,
    // The desktop table gives the timestamp a column of its own once there is
    // room for one, so it passes 'md:hidden' here rather than printing it
    // twice. Which width that happens at is a CSS question, so it is answered
    // with a class instead of a second render path.
    'timestampClass' => '',
])

{{-- Market, freshness and distance qualify a single row rather than being
things you compare across rows, so they ride under the name instead of taking
three columns of their own. Only the timestamp goes amber when stale - tinting
the whole line would read as a verdict on the organization. --}}
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
        {{-- Nobody comes here to read a rate; they come to go and exchange
            money. This is the last step of that, so it is a real link out to
            whatever maps app the device has, not another page of ours. --}}
        <a
            href="{{ $directions['url'] }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1 underline hover:text-ink"
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
