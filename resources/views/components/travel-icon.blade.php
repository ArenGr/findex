@props(['name'])

{{--
    The old hand-drawn travel set, now a thin alias onto the one icon family.

    Kept so the ~35 existing call sites did not all have to change in one
    commit; new markup should use <x-icon name="..."> directly. The class
    attribute still sizes it, as it always did.
--}}
@php
    $lucide = [
        'arrow_forward' => 'arrow-right',
        'arrow_back' => 'arrow-left',
        'check' => 'check',
        'close' => 'x',
        'plus' => 'plus',
        'minus' => 'minus',
        'flight_takeoff' => 'plane-takeoff',
        'flight' => 'plane',
        'calendar_month' => 'calendar-days',
        'location_on' => 'map-pin',
        'group' => 'users',
        'family' => 'users',
        'tune' => 'sliders-horizontal',
        'hotel' => 'bed-double',
        'restaurant' => 'utensils',
        'wallet' => 'wallet',
        'sell' => 'tag',
        'map' => 'map',
        'star' => 'star',
        'shield' => 'shield-check',
        'shield_check' => 'shield-check',
        'clock' => 'clock',
        'lock' => 'lock',
        'document' => 'file-text',
        'mail' => 'mail',
        'compare' => 'scale',
        'luggage' => 'luggage',
        'lightbulb' => 'lightbulb',
    ][$name] ?? $name;
@endphp

<x-lucide :name="$lucide" {{ $attributes }} />
