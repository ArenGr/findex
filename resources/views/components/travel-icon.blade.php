@props(['name', 'filled' => false])

@php
    /**
     * The Material Symbols the travel designs call for, drawn as inline SVG
     * rather than pulled in as a webfont: the whole icon font is a few
     * hundred KB to render the dozen or so glyphs used here, and every
     * other icon in this app is already an inline SVG.
     *
     * 24x24 outline paths, stroked with currentColor so they inherit text
     * colour and size from whatever they sit in.
     */
    $paths = match ($name) {
        'flight_takeoff' => ['M2.5 19h19', 'M3.4 12.6l2.3.6 2.6-2.6-5.6-3.1 1.6-1.6 7.4 1.6 3.3-3.3a2 2 0 1 1 2.8 2.8l-3.3 3.3 1.6 7.4-1.6 1.6-3.1-5.6-2.6 2.6.6 2.3-1.2 1.2-2-3.6-3.6-2 1.2-1.2Z'],
        'tune' => ['M4 7h10', 'M18 7h2', 'M4 17h4', 'M12 17h8', 'M16 5v4', 'M10 15v4'],
        'star' => ['M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5Z'],
        'wallet' => ['M3 7.5A2.5 2.5 0 0 1 5.5 5H18a2 2 0 0 1 2 2v1', 'M3 7.5V17a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-2.5', 'M20 10.5h-4a2 2 0 0 0 0 4h4v-4Z'],
        'location_on' => ['M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z', 'M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z'],
        'search' => ['M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z', 'M20 20l-4-4'],
        'group' => ['M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M2.5 19.5a6.5 6.5 0 0 1 13 0', 'M16.5 11.2A3 3 0 0 0 17 5.2', 'M18 19.5a5.6 5.6 0 0 0-2-4.3'],
        'expand_more' => ['M6 9.5l6 6 6-6'],
        'calendar_month' => ['M4 6.5A1.5 1.5 0 0 1 5.5 5h13A1.5 1.5 0 0 1 20 6.5v12A1.5 1.5 0 0 1 18.5 20h-13A1.5 1.5 0 0 1 4 18.5v-12Z', 'M4 10h16', 'M8.5 3v4', 'M15.5 3v4'],
        'hotel' => ['M3 19v-8', 'M3 13h18v6', 'M21 19v-4a3 3 0 0 0-3-3H9v7', 'M6.5 10.5a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z'],
        'payments' => ['M3 8.5A1.5 1.5 0 0 1 4.5 7h11A1.5 1.5 0 0 1 17 8.5v6a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 14.5v-6Z', 'M10 14a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z', 'M20 9.5v8A1.5 1.5 0 0 1 18.5 19H7'],
        'info' => ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 11v5', 'M12 8h.01'],
        'arrow_forward' => ['M4 12h15', 'M13 6l6 6-6 6'],
        'arrow_back' => ['M20 12H5', 'M11 18l-6-6 6-6'],
        'sell' => ['M3 11.5V5a2 2 0 0 1 2-2h6.5a2 2 0 0 1 1.4.6l8 8a2 2 0 0 1 0 2.8l-6.5 6.5a2 2 0 0 1-2.8 0l-8-8a2 2 0 0 1-.6-1.4Z', 'M7.5 8.5h.01'],
        'flight' => ['M21 15.5l-8.5-2.3v-5a1.7 1.7 0 1 0-3.4 0v5L3 15.5v2l6.1-1.4v3.3l-2 1.3v1.1l3.7-.9 3.7.9v-1.1l-2-1.3v-3.3L21 17.5v-2Z'],
        'map' => ['M9 4.5L3.5 6.8v12.7L9 17.2m0-12.7l6 2.3m-6-2.3v12.7m6-10.4l5.5-2.3v12.7L15 19.5m0-12.7v12.7m0 0l-6-2.3'],
        'restaurant' => ['M6 3v7a2.5 2.5 0 0 0 5 0V3', 'M8.5 10v11', 'M17 3c-1.5 1.5-2 3.5-2 5.5S15.5 12 17 12.5V21'],
        'family' => ['M7 8.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z', 'M17 8.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z', 'M3.5 20v-5a3.5 3.5 0 0 1 7 0v5', 'M13.5 20v-5a3.5 3.5 0 0 1 7 0v5'],
        'cancel_free' => ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M9 12l2 2 4-4'],
        'check' => ['M5 12.5l4.5 4.5L19 7.5'],
        'close' => ['M6 6l12 12', 'M18 6L6 18'],
        'lock' => ['M6 10.5h12a1.5 1.5 0 0 1 1.5 1.5v7A1.5 1.5 0 0 1 18 20.5H6A1.5 1.5 0 0 1 4.5 19v-7A1.5 1.5 0 0 1 6 10.5Z', 'M8.5 10.5V7.5a3.5 3.5 0 1 1 7 0v3'],
        'shield' => ['M12 3l7 3v5.5c0 4.3-2.9 8-7 9.5-4.1-1.5-7-5.2-7-9.5V6l7-3Z'],
        default => [],
    };
@endphp

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }}
>
    @foreach ($paths as $path)
        <path d="{{ $path }}" @if ($filled) fill="currentColor" stroke="none" @endif />
    @endforeach
</svg>
