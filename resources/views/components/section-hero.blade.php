@props([
    'tone' => 'insurance',  // which page accent washes the band - see --color-tone-* in app.css
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

{{-- The top of a page: what it is, said once, over that page's own accent. --}}
<section class="section-hero" style="--tone: var(--color-tone-{{ $tone }}, var(--color-primary))">
    <div class="site-container py-14 lg:py-20">
        <div class="max-w-3xl">
            @if ($eyebrow)
                <p class="hero-accent-text flex items-center gap-3 text-xs font-bold tracking-[0.18em] uppercase">
                    <span class="hero-accent-rule h-px w-8 shrink-0"></span>
                    {{ $eyebrow }}
                </p>
            @endif

            <h1 @class(['font-heading text-4xl leading-tight font-bold break-words text-ink sm:text-5xl', 'mt-5' => $eyebrow])>{{ $title }}</h1>

            @if ($subtitle)
                <p class="mt-5 max-w-2xl text-lg leading-relaxed text-muted">{{ $subtitle }}</p>
            @endif

            @if (trim($slot) !== '')
                <div class="mt-8">{{ $slot }}</div>
            @endif
        </div>
    </div>
</section>
