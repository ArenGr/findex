@props(['label', 'align' => 'right'])

{{--
    A "?" that explains a control without spending a paragraph of page space on
    it. Used where the label alone is not self-evident but the explanation only
    matters to the people who ask for it.
--}}
<div x-data="{ open: false }" class="relative inline-flex">
    <button
        type="button"
        @click="open = !open"
        @keydown.escape.window="open = false"
        :aria-expanded="open"
        aria-label="{{ $label }}"
        class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-border-muted text-xs font-semibold text-muted transition hover:border-primary hover:text-primary"
    >
        ?
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        {{-- Capped against the viewport so the panel can't be the thing that
        pushes a narrow page sideways. --}}
        class="absolute top-8 z-30 w-64 max-w-[calc(100vw-3rem)] rounded-xl border border-placeholder bg-white p-4 text-sm leading-relaxed break-words text-muted shadow-lg {{ $align === 'left' ? 'left-0' : 'right-0' }}"
    >
        {{ $slot }}
    </div>
</div>
