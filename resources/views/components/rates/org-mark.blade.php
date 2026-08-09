@props(['logo' => null, 'name' => ''])

{{-- Most organizations have no logo, so the fallback appears on nearly every
row and has to stay quiet rather than read as a badge. --}}
@if ($logo)
    <img src="{{ $logo }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-contain">
@else
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-placeholder/40 text-xs font-semibold text-muted">
        {{ Str::of($name)->substr(0, 1)->upper() }}
    </span>
@endif
