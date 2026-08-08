@props(['name'])

@php
    /*
     * Per-icon colours rather than currentColor: these are meant to read as
     * distinct product areas at a glance, so each keeps its own hue in every
     * state. The link's text still recolours on hover/active, which is what
     * carries the interaction feedback.
     *
     * Hex rather than theme tokens because the existing *-tint/*-line pairs
     * are pale border/background colours - far too light to stroke a 18px
     * glyph with. These are the same hues at usable weight, plus the tint
     * used as a soft fill so the icons read as duotone rather than outline.
     */
    $ink = [
        'rates' => '#607e34',      // primary green - money
        'ratesAlt' => '#005fb9',   // second currency in the exchange arrows
        'banking' => '#005fb9',    // accent blue - institutions
        'bankingFill' => '#dbeafe',
        'insurance' => '#c8971f',  // gold, as used by the rate-alert bell
        'insuranceFill' => '#fdf3d7',
        'insuranceMark' => '#607e34',
        'travel' => '#0e8fa0',     // teal - globe
        'travelFill' => '#e0f2f4',
        'about' => '#7161a8',      // muted violet
        'aboutFill' => '#efeaf7',
    ];
@endphp

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    {{ $attributes->merge(['class' => 'h-[18px] w-[18px] shrink-0']) }}
>
    @switch($name)
        @case('rates')
            {{-- Two currencies swapping, one arrow per colour --}}
            <path d="M3.5 8.5h14l-3.2-3.2" stroke="{{ $ink['rates'] }}" />
            <path d="M20.5 15.5h-14l3.2 3.2" stroke="{{ $ink['ratesAlt'] }}" />
            @break

        @case('banking')
            {{-- Bank facade: filled pediment over columns --}}
            <path d="M3.5 9.8 12 4.5l8.5 5.3z" fill="{{ $ink['bankingFill'] }}" stroke="{{ $ink['banking'] }}" />
            <path d="M6.2 11v6.5M10.1 11v6.5M13.9 11v6.5M17.8 11v6.5" stroke="{{ $ink['banking'] }}" />
            <path d="M3.8 19.6h16.4" stroke="{{ $ink['banking'] }}" />
            @break

        @case('insurance')
            {{-- Shield, with the tick in green so "covered" reads instantly --}}
            <path d="M12 3.4 19 6v5.2c0 4.3-2.9 7.6-7 9.4-4.1-1.8-7-5.1-7-9.4V6z" fill="{{ $ink['insuranceFill'] }}" stroke="{{ $ink['insurance'] }}" />
            <path d="M9.2 11.9 11.3 14l3.6-3.7" stroke="{{ $ink['insuranceMark'] }}" />
            @break

        @case('travel')
            {{-- Globe --}}
            <circle cx="12" cy="12" r="8.4" fill="{{ $ink['travelFill'] }}" stroke="{{ $ink['travel'] }}" />
            <path d="M3.6 12h16.8" stroke="{{ $ink['travel'] }}" />
            <path d="M12 3.6c2.4 2.5 2.4 14.3 0 16.8-2.4-2.5-2.4-14.3 0-16.8z" stroke="{{ $ink['travel'] }}" />
            @break

        @case('about')
            {{-- Info --}}
            <circle cx="12" cy="12" r="8.4" fill="{{ $ink['aboutFill'] }}" stroke="{{ $ink['about'] }}" />
            <path d="M12 11.3v4.8" stroke="{{ $ink['about'] }}" />
            <path d="M12 8.1h.01" stroke="{{ $ink['about'] }}" />
            @break
    @endswitch
</svg>
