@props(['branch'])

@php
    $open = $branch->isOpenAt();
@endphp

{{--
    Three states, not two. A branch whose hours we have never recorded is not a
    closed one, and saying "closed" about it would send someone away from an
    open door - so it says plainly that we do not know.

    Open and closed are spelled out in words as well as coloured: colour alone
    reaches neither a screen reader nor anyone who cannot separate the two.

    This answers for right now only. The week's schedule sits beside it in
    x-branch-week, which is where today's times are printed - repeating them
    here put the same figures on screen twice.
--}}
<span class="inline-flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
    @if ($open === null)
        <span class="text-muted">{{ __('rates.hours_unknown') }}</span>
    @else
        <span @class(['font-semibold', 'text-primary' => $open, 'text-accent-red' => ! $open])>
            {{ $open ? __('rates.open') : __('rates.closed') }}
        </span>
    @endif
</span>
