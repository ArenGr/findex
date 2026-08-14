@props(['label', 'align' => 'right'])

{{--
    A "?" that explains a control without spending a paragraph of page space on
    it. Used where the label alone is not self-evident but the explanation only
    matters to the people who ask for it.
--}}
{{-- The button is the tap target and the circle inside it is the picture, so
the thing a thumb has to hit is 44px while the page still shows a 24px "?".
Five of these sit on a phone screen at once; at 24px they were unhittable, and
growing the circle instead would have made the page look like it was shouting.

The negative margin keeps the larger target out of the layout, so nothing
around it moved. --}}
<div x-data="{ open: false }" class="relative -m-2.5 inline-flex">
    <button
        type="button"
        @click="open = !open"
        @keydown.escape.window="open = false"
        :aria-expanded="open"
        aria-label="{{ $label }}"
        class="group inline-flex h-11 w-11 shrink-0 items-center justify-center"
    >
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-border-muted text-xs font-semibold text-muted transition group-hover:border-primary group-hover:text-primary">
            ?
        </span>
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        {{-- Capped against the viewport so the panel can't be the thing that
        pushes a narrow page sideways. --}}
        class="absolute top-11 z-30 w-64 max-w-[calc(100vw-3rem)] rounded-xl border border-placeholder bg-white p-4 text-sm leading-relaxed break-words text-muted shadow-lg {{ $align === 'left' ? 'left-0' : 'right-0' }}"
    >
        {{ $slot }}
    </div>
</div>
