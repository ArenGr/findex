@props(['count' => 1, 'label' => null])

@php
    // Six banks quoting 368.00 all win, and six stars with no explanation read
    // as a bug rather than as a tie. The label says how many share it, so the
    // repetition is accounted for wherever the star is read from - tooltip,
    // screen reader, or hover.
    $label = $label ?? ($count > 1
        ? __('rates.best_badge_shared', ['count' => $count])
        : __('rates.best_badge'));
@endphp

{{-- The same star the review rating draws, in the same yellow, so "marked
with a star" means one thing across the site. --}}
<svg
    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
    class="h-4 w-4 shrink-0 fill-accent-yellow"
    role="img" aria-label="{{ $label }}"
>
    <title>{{ $label }}</title>
    <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
</svg>
