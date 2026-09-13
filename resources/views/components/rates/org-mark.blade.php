@props(['logo' => null, 'name' => ''])

{{-- A landscape slot, not a square. --}}
<span class="flex h-10 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-placeholder bg-white">
    @if ($logo)
        <img src="{{ $logo }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-contain p-1">
    @else
        <span class="text-sm font-semibold text-muted">{{ Str::of($name)->substr(0, 1)->upper() }}</span>
    @endif
</span>
