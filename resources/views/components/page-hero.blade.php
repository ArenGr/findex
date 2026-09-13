@props([
    'title',
    'subtitle' => null,
])

@php
    $columns = isset($illustration) ? 'lg:grid-cols-[minmax(0,1fr)_440px]' : 'lg:grid-cols-1';
@endphp

{{-- No overflow-hidden. --}}
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

            {{-- Whatever the page puts under the subtitle: CTAs, reassurance chips, a back link. --}}
            @if (trim($slot) !== '')
                <div class="mt-7">{{ $slot }}</div>
            @endif
        </div>

        @isset($illustration)
            <div class="hidden h-[230px] items-center justify-end lg:flex [&>*]:h-full [&>*]:w-full [&_img]:h-full [&_img]:w-full [&_img]:object-contain [&_img]:object-right">
                {{ $illustration }}
            </div>
        @endisset
    </div>

    {{-- Progress for the multi-step flows. --}}
    @isset($steps)
        <div class="site-container">
            <div class="border-t border-primary/15 py-6">{{ $steps }}</div>
        </div>
    @endisset
</section>
