@props(['name'])

{{-- The four "how it works" illustrations. --}}
@php
    $green = '#607E34';
    $greenSoft = '#A9C07F';
    $blue = '#1256C4';
    $blueSoft = '#8FB4E8';
    $yellow = '#F4C430';
    $ink = '#0D1C2D';
    $paper = '#FFFFFF';
@endphp

<svg viewBox="0 0 48 48" fill="none" aria-hidden="true" {{ $attributes->merge(['class' => 'h-12 w-12']) }}>
    @switch ($name)
        {{-- 1. Tell us about your trip: a form with answers already on it. --}}
        @case ('brief')
            <rect x="9" y="5" width="26" height="38" rx="4" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="2"/>
            <path d="M17 5h10v4a2 2 0 0 1-2 2h-6a2 2 0 0 1-2-2V5Z" fill="{{ $green }}"/>
            <rect x="14" y="17" width="9" height="2.6" rx="1.3" fill="{{ $blueSoft }}"/>
            <rect x="14" y="24" width="16" height="2.6" rx="1.3" fill="{{ $blueSoft }}"/>
            <rect x="14" y="31" width="12" height="2.6" rx="1.3" fill="{{ $blueSoft }}"/>
            {{-- The tick that says it is answered, not blank. --}}
            <circle cx="35" cy="33" r="9" fill="{{ $green }}"/>
            <path d="m31 33 3 3 5.5-6" stroke="{{ $paper }}" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="31" cy="9" r="3" fill="{{ $yellow }}"/>
            @break

        {{-- 2. Agencies respond: replies arriving, more than one. --}}
        @case ('replies')
            <rect x="14" y="7" width="26" height="17" rx="3" fill="{{ $paper }}" stroke="{{ $blueSoft }}" stroke-width="2"/>
            <rect x="10" y="13" width="28" height="19" rx="3" fill="{{ $paper }}" stroke="{{ $blue }}" stroke-width="2"/>
            <path d="M11.5 15.5 22.8 24a2 2 0 0 0 2.4 0l11.3-8.5" stroke="{{ $blue }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            {{-- Three of them, which is the whole point of one request. --}}
            <circle cx="12" cy="38" r="4" fill="{{ $green }}"/>
            <circle cx="24" cy="38" r="4" fill="{{ $greenSoft }}"/>
            <circle cx="36" cy="38" r="4" fill="{{ $yellow }}"/>
            @break

        {{-- 3. Compare offers: two quotes side by side, one chosen. --}}
        @case ('compare')
            <rect x="5" y="12" width="17" height="29" rx="3" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="2"/>
            <rect x="9" y="18" width="9" height="2.4" rx="1.2" fill="{{ $blueSoft }}"/>
            <rect x="9" y="24" width="7" height="2.4" rx="1.2" fill="{{ $blueSoft }}"/>
            <rect x="9" y="31" width="10" height="4" rx="2" fill="{{ $blueSoft }}"/>

            <rect x="26" y="7" width="17" height="34" rx="3" fill="{{ $paper }}" stroke="{{ $green }}" stroke-width="2.4"/>
            <rect x="30" y="14" width="9" height="2.4" rx="1.2" fill="{{ $greenSoft }}"/>
            <rect x="30" y="20" width="7" height="2.4" rx="1.2" fill="{{ $greenSoft }}"/>
            <rect x="30" y="27" width="10" height="4" rx="2" fill="{{ $green }}"/>
            {{-- The winning card is taller and carries the badge. --}}
            <circle cx="40" cy="9" r="6" fill="{{ $yellow }}"/>
            <path d="m37.4 9 1.9 1.9 3.4-3.6" stroke="{{ $ink }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            @break

        @case ('depart')
            <circle cx="10" cy="11" r="5" fill="{{ $yellow }}"/>
            <path d="M26 31c2.5-4.5 5-9 7.5-13" stroke="{{ $greenSoft }}" stroke-width="2" stroke-linecap="round" stroke-dasharray="3 4"/>
            <g transform="translate(37 12) rotate(42) scale(0.78) translate(-12 -12)">
                <path d="M21 15.6v-1.8l-7.6-4.8V3.4a1.4 1.4 0 0 0-2.8 0V9l-7.6 4.8v1.8l7.6-2.4v4.9l-2 1.4v1.3l3.4-.9 3.4.9v-1.3l-2-1.4v-4.9l7.6 2.4Z" fill="{{ $blue }}"/>
            </g>
            <path d="M5 43h20" stroke="{{ $ink }}" stroke-width="2.4" stroke-linecap="round"/>
            <path d="M12.5 27.5v-1.8a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v1.8" stroke="{{ $ink }}" stroke-width="2" stroke-linecap="round"/>
            <rect x="7" y="27.5" width="16" height="13" rx="3" fill="{{ $paper }}" stroke="{{ $ink }}" stroke-width="2"/>
            <rect x="11" y="27.5" width="8" height="13" fill="{{ $green }}"/>
            @break
    @endswitch
</svg>
