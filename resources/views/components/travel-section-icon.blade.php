@props(['name', 'done' => null])

{{--
    The glyph beside a wizard section's heading.

    Four sections hand-rolled the same span, the same two icons and the same
    "turn solid once this section is answered" binding, and they had already
    drifted - one carried a different padding, another a different icon size.

    `done` is an Alpine expression; pass it and the disc fills with the brand
    green and swaps to a tick when it evaluates true. Leave it off for a
    section that has no completed state.
--}}
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
