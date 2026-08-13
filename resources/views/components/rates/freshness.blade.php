@props(['scrapedAt', 'stale' => false, 'changedAt' => null])

@php
    $moment = \Illuminate\Support\Carbon::parse($scrapedAt);

    // Two different facts, and the second is the more useful one: "checked 22
    // hours ago" is true of every bank at once, while "unchanged for a week"
    // tells them apart. It rides in the title rather than on the row - one more
    // line per row is the last thing this table needs.
    $title = __('rates.checked_at', ['time' => $moment->diffForHumans()]);

    if ($changedAt) {
        $title .= ' · '.__('rates.unchanged_since', [
            'time' => \Illuminate\Support\Carbon::parse($changedAt)->diffForHumans(),
        ]);
    }
@endphp

{{--
    Staleness used to be amber text and nothing else, which says nothing to a
    screen reader and nothing to the ~8% of men who cannot separate it from the
    grey beside it. The warning triangle carries the same meaning without
    relying on the colour, and the title spells it out in words.
--}}
<span
    @class(['inline-flex items-center gap-1', 'text-[#B4791F]' => $stale, 'text-muted' => ! $stale])
    title="{{ $title }}"
>
    @if ($stale)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0" role="img" aria-label="{{ __('rates.stale_label') }}">
            <title>{{ __('rates.stale_label') }}</title>
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0" />
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
        </svg>
    @endif
    <span>{{ $moment->diffForHumans() }}</span>
</span>
