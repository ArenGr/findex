@props(['logo' => null, 'name' => ''])

{{-- Square, because these are wordmarks: a circular crop cuts the ends off
"ACBA BANK". Most organizations have no logo at all, so the fallback appears on
nearly every row and has to stay quiet rather than read as a badge. --}}
<span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-placeholder bg-white">
    @if ($logo)
        <img src="{{ $logo }}" alt="" class="h-full w-full object-contain p-1">
    @else
        <span class="text-sm font-semibold text-muted">{{ Str::of($name)->substr(0, 1)->upper() }}</span>
    @endif
</span>
