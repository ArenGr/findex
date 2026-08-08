@props(['name'])

@php
    /*
     * Colours are referenced as CSS custom properties, not hex, so these
     * icons track resources/css/app.css's @theme block - retune a brand
     * colour there and the nav follows. Tailwind v4 emits every @theme
     * entry on :root, which is what makes var() resolve inside these
     * presentation attributes.
     *
     * One hue per nav item, taken from the documented palette (see
     * /style-guide): the brand trio plus two of the slide accents, which
     * together give five distinguishable product areas.
     *
     * Each glyph is its own colour at full strength for the stroke and 20%
     * for the fill, which is what makes them read as duotone without
     * needing a separate tint token per hue.
     */
    $hue = [
        'rates' => 'var(--color-primary)',
        'ratesAlt' => 'var(--color-accent-blue)',
        'banking' => 'var(--color-accent-blue)',
        'insurance' => 'var(--color-accent-yellow)',
        'insuranceMark' => 'var(--color-primary)',
        'travel' => 'var(--color-slide-blue)',
        'about' => 'var(--color-slide-purple)',
    ];
@endphp

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke-width="1.9"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    {{ $attributes->merge(['class' => 'h-[18px] w-[18px] shrink-0']) }}
>
    @switch($name)
        @case('rates')
            {{-- Two currencies swapping, one arrow per colour --}}
            <path d="M3.5 8.5h14l-3.2-3.2" stroke="{{ $hue['rates'] }}" />
            <path d="M20.5 15.5h-14l3.2 3.2" stroke="{{ $hue['ratesAlt'] }}" />
            @break

        @case('banking')
            {{-- Bank facade: filled pediment over columns --}}
            <path d="M3.5 9.8 12 4.5l8.5 5.3z" fill="{{ $hue['banking'] }}" fill-opacity="0.35" stroke="{{ $hue['banking'] }}" />
            <path d="M6.2 11v6.5M10.1 11v6.5M13.9 11v6.5M17.8 11v6.5" stroke="{{ $hue['banking'] }}" />
            <path d="M3.8 19.6h16.4" stroke="{{ $hue['banking'] }}" />
            @break

        @case('insurance')
            {{-- Shield, with the tick in green so "covered" reads instantly --}}
            <path d="M12 3.4 19 6v5.2c0 4.3-2.9 7.6-7 9.4-4.1-1.8-7-5.1-7-9.4V6z" fill="{{ $hue['insurance'] }}" fill-opacity="0.35" stroke="{{ $hue['insurance'] }}" />
            <path d="M9.2 11.9 11.3 14l3.6-3.7" stroke="{{ $hue['insuranceMark'] }}" />
            @break

        @case('travel')
            {{-- Globe --}}
            <circle cx="12" cy="12" r="8.4" fill="{{ $hue['travel'] }}" fill-opacity="0.35" stroke="{{ $hue['travel'] }}" />
            <path d="M3.6 12h16.8" stroke="{{ $hue['travel'] }}" />
            <path d="M12 3.6c2.4 2.5 2.4 14.3 0 16.8-2.4-2.5-2.4-14.3 0-16.8z" stroke="{{ $hue['travel'] }}" />
            @break

        @case('about')
            {{-- Info --}}
            <circle cx="12" cy="12" r="8.4" fill="{{ $hue['about'] }}" fill-opacity="0.35" stroke="{{ $hue['about'] }}" />
            <path d="M12 11.3v4.8" stroke="{{ $hue['about'] }}" />
            <path d="M12 8.1h.01" stroke="{{ $hue['about'] }}" />
            @break
    @endswitch
</svg>
