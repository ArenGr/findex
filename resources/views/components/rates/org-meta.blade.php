@props(['market' => null, 'scrapedAt' => null, 'stale' => false, 'distance' => null])

{{-- Market, freshness and distance qualify a single row rather than being
things you compare across rows, so they ride under the name instead of taking
three columns of their own. Only the timestamp goes amber when stale - tinting
the whole line would read as a verdict on the organization. --}}
<span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs break-words text-muted">
    @if ($market)
        <span>{{ $market }}</span>
    @endif

    @if ($scrapedAt)
        @if ($market)
            <span aria-hidden="true">&middot;</span>
        @endif
        <span @class(['text-[#B4791F]' => $stale])>
            {{ \Illuminate\Support\Carbon::parse($scrapedAt)->diffForHumans() }}
        </span>
    @endif

    @if ($distance)
        @if ($market || $scrapedAt)
            <span aria-hidden="true">&middot;</span>
        @endif
        <span>{{ $distance }}</span>
    @endif
</span>
