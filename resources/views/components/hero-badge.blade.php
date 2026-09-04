{{--
    The eyebrow badge above a hero heading.

    Its geometry is fixed here so every page's badge is the same object: the
    two that existed differed in font size (15px on insurance, 13px on travel)
    and so sat at different heights above otherwise identical headings.

    Pass an icon through the `icon` slot; the label is the default slot.
--}}
@props(['icon' => null])

<span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-primary/10 px-3 text-[13px] font-semibold tracking-[0.01em] text-primary">
    @isset($icon)
        <span class="flex h-4 w-4 shrink-0 items-center justify-center">{{ $icon }}</span>
    @endisset
    {{ $slot }}
</span>
