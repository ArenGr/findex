@props(['branch'])

@php
    $open = $branch->isOpenAt();
    $today = $branch->hoursOn(now());
@endphp

{{--
    Three states, not two. A branch whose hours we have never recorded is not a
    closed one, and saying "closed" about it would send someone away from an
    open door - so it says plainly that we do not know.

    Open and closed are spelled out in words as well as coloured: colour alone
    reaches neither a screen reader nor anyone who cannot separate the two.
--}}
<span class="inline-flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
    @if ($open === null)
        <span class="text-muted">{{ __('rates.hours_unknown') }}</span>
    @else
        <span @class(['font-semibold', 'text-primary' => $open, 'text-accent-red' => ! $open])>
            {{ $open ? __('rates.open') : __('rates.closed') }}
        </span>

        @if ($today)
            <span class="text-muted tabular-nums">{{ $today[0] }} &ndash; {{ $today[1] }}</span>
        @endif
    @endif
</span>
