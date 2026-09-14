@props([
    'title',
    'subtitle' => null,
])

{{--
    The top of every page but the home page.

    Findex does one thing on each of them - collect what the visitor wants,
    send it, compare what comes back - so they all open the same way:

        eyebrow      the category this page belongs to
        title        one line
        subtitle     one short explanation
        panel        the thing that collects the answer (search, form, filters)
        facts        a quiet row of real numbers under it
        illustration optional, small, to the right

    A page that fills different slots still occupies the same container, the
    same proportions and the same spacing as every other.
--}}
@php
    $hasIllustration = isset($illustration);
    $columns = $hasIllustration ? 'lg:grid-cols-[minmax(0,1fr)_440px]' : 'lg:grid-cols-1';

    // The panel wants the full column; an illustration beside a search box
    // leaves neither enough room.
    $columns = isset($panel) ? 'lg:grid-cols-1' : $columns;
@endphp

{{-- No overflow-hidden. --}}
<section {{ $attributes->merge(['class' => 'border-b border-border bg-surface-alt']) }}>
    <div class="site-container grid items-center gap-10 pt-16 lg:pt-20 {{ $columns }} {{ isset($steps) || isset($panel) ? 'pb-10 lg:pb-12' : 'pb-16 lg:pb-20' }}">
        <div class="min-w-0">
            @isset($eyebrow)
                <div class="mb-4">{{ $eyebrow }}</div>
            @endisset

            <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink sm:text-4xl lg:text-5xl">{{ $title }}</h1>

            @if ($subtitle)
                <p class="mt-4 max-w-2xl text-base leading-relaxed break-words text-muted lg:text-lg lg:leading-8">{{ $subtitle }}</p>
            @endif

            {{-- Whatever the page puts under the subtitle: CTAs, reassurance chips, a back link. --}}
            @if (trim($slot) !== '')
                <div class="mt-7">{{ $slot }}</div>
            @endif
        </div>

        @if ($hasIllustration && ! isset($panel))
            <div class="hidden h-[230px] items-center justify-end lg:flex [&>*]:h-full [&>*]:w-full [&_img]:h-full [&_img]:w-full [&_img]:object-contain [&_img]:object-right">
                {{ $illustration }}
            </div>
        @endif
    </div>

    {{-- The collect step. One white panel on the section tint, on every page
         that asks the visitor for something. --}}
    @isset($panel)
        <div class="site-container">
            <div class="rounded-3xl border border-border bg-surface p-5 sm:p-6">{{ $panel }}</div>
        </div>
    @endisset

    {{-- Real numbers rather than decoration - see the note in the brief. --}}
    @isset($facts)
        <div class="site-container">
            <ul class="flex flex-wrap items-center gap-x-6 gap-y-2 pt-6 text-sm text-muted">{{ $facts }}</ul>
        </div>
    @endisset

    {{-- Progress for the multi-step flows. --}}
    @isset($steps)
        <div class="site-container">
            <div class="border-t border-border py-6">{{ $steps }}</div>
        </div>
    @endisset

    @if (isset($panel) || isset($facts))
        <div class="pb-12 lg:pb-16"></div>
    @endif
</section>
