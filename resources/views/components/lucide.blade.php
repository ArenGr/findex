@props(['name', 'size' => 20, 'stroke' => 1.75])

{{--
    The one icon family: Lucide, outline, same stroke everywhere.
    Named x-lucide, not x-icon: blade-icons (via Filament) already owns x-icon.

        <x-lucide name="shield-check" />        20px, the default
        <x-lucide name="plane" :size="24" />    a feature icon
        <x-lucide name="star" :size="16" />     inline with small text

    Names come from resources/icons/lucide.php - add one to NAMES in
    tools/build-icons.mjs and run `npm run icons`. An unknown name renders
    nothing rather than breaking the page, and says so in the log.
--}}
@php
    $paths = once(fn () => require resource_path('icons/lucide.php'));
    $body = $paths[$name] ?? null;

    if ($body === null) {
        \Illuminate\Support\Facades\Log::warning('Unknown icon', ['name' => $name]);
    }
@endphp

@if ($body)
    <svg
        {{ $attributes->merge(['class' => 'shrink-0']) }}
        width="{{ $size }}"
        height="{{ $size }}"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="{{ $stroke }}"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >{!! $body !!}</svg>
@endif
