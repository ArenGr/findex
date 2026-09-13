@props(['name', 'done' => null])

{{-- The glyph beside a wizard section's heading. --}}
<span
    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-travel-100 text-travel-700 transition-colors"
    @if ($done) :class="({{ $done }}) && '!bg-travel-600 !text-white'" @endif
>
    @if ($done)
        <x-travel-icon name="check" class="h-4 w-4" x-show="{{ $done }}" x-cloak />
        <x-travel-icon :name="$name" class="h-4 w-4" x-show="!({{ $done }})" />
    @else
        <x-travel-icon :name="$name" class="h-4 w-4" />
    @endif
</span>
