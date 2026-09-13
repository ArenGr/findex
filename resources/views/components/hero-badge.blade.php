{{-- The eyebrow badge above a hero heading. --}}
@props(['icon' => null])

<span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-primary/10 px-3 text-[13px] font-semibold tracking-[0.01em] text-primary">
    @isset($icon)
        <span class="flex h-4 w-4 shrink-0 items-center justify-center">{{ $icon }}</span>
    @endisset
    {{ $slot }}
</span>
