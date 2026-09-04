@props([
    'title',
    'subtitle' => null,
])

@php
    // Every hero on the site is this component, built the way the about page
    // builds its own: a full-width band, its content in the site's column, and
    // a rule underneath where the page proper begins.
    //
    // One background for all of them - bg-primary/5, the about page's - rather
    // than a tint per section. A page's identity comes from its illustration
    // and its content, not from the shade behind them. (There was a five-tint
    // system here; it is gone rather than left as five identical values, which
    // would only invite someone to set them apart again.)
    $columns = isset($illustration) ? 'lg:grid-cols-[minmax(0,1fr)_440px]' : 'lg:grid-cols-1';
@endphp

{{-- No overflow-hidden. The about page's hero carries it because its own
     decorative backdrop bleeds past the edge; here it clipped anything that
     opens downward out of the hero - the "?" popovers beside the rates CTAs
     lost the bottom two thirds of their text. --}}
<section {{ $attributes->merge(['class' => 'border-b border-placeholder bg-primary/5']) }}>
    <div class="site-container grid items-center gap-10 pt-16 lg:pt-20 {{ $columns }} {{ isset($steps) ? 'pb-10 lg:pb-12' : 'pb-16 lg:pb-20' }}">
        <div class="min-w-0">
            @isset($eyebrow)
                <div class="mb-4">{{ $eyebrow }}</div>
            @endisset

            <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink sm:text-4xl lg:text-5xl">{{ $title }}</h1>

            @if ($subtitle)
                <p class="mt-4 max-w-2xl text-base leading-relaxed break-words text-muted lg:text-lg lg:leading-8">{{ $subtitle }}</p>
            @endif

            {{-- Whatever the page puts under the subtitle: CTAs, reassurance
                 chips, a back link. Spaced once here so every hero's rhythm
                 matches regardless of what fills it. --}}
            @if (trim($slot) !== '')
                <div class="mt-7">{{ $slot }}</div>
            @endif
        </div>

        {{-- One box for every page's artwork, so the illustrations carry the
             same visual weight and sit on the same baseline. --}}
        @isset($illustration)
            <div class="hidden h-[230px] items-center justify-end lg:flex [&>*]:h-full [&>*]:w-full [&_img]:h-full [&_img]:w-full [&_img]:object-contain [&_img]:object-right">
                {{ $illustration }}
            </div>
        @endisset
    </div>

    {{-- Progress for the multi-step flows. It sits inside the hero, on the
         tint, under a hairline - so "what this page is" and "where you are in
         it" are one block, rather than a white card floating between the hero
         and the form. --}}
    @isset($steps)
        <div class="site-container">
            <div class="border-t border-primary/15 py-6">{{ $steps }}</div>
        </div>
    @endisset
</section>
