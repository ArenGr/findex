@props([
    'heading',
    'sub' => null,
    'steps',
    'tone' => null,   // which page accent colours the cards - see --color-tone-* in app.css
])

{{-- Four numbered steps, threaded together. Shared by rates, insurance, travel and visa.
     `steps` is a list of ['icon' => lucide name, 'title' => ..., 'body' => ...]. --}}
<section class="section-steps" style="--tone: var(--color-tone-{{ $tone }}, var(--color-primary))">
    <div class="site-container py-16 lg:py-20">
        <div class="max-w-2xl">
            <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ $heading }}</h2>
            @if ($sub)
                <p class="mt-3 text-base leading-relaxed text-muted">{{ $sub }}</p>
            @endif
        </div>

        <ol class="relative mt-10 grid gap-5 sm:grid-cols-2 lg:mt-12 lg:grid-cols-4 lg:gap-6">
            <span class="step-thread pointer-events-none absolute top-[2.9rem] right-10 left-10 hidden h-0.5 rounded-full lg:block" aria-hidden="true"></span>

            @foreach ($steps as $i => $step)
                <li class="step-card relative overflow-hidden rounded-2xl border border-border bg-surface p-6 transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_40px_-24px_rgb(24_29_18/0.35)]">
                    <span class="step-number pointer-events-none absolute top-3 right-4 font-heading text-5xl leading-none font-bold" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</span>

                    <span class="step-icon relative flex h-12 w-12 items-center justify-center rounded-xl">
                        <x-lucide :name="$step['icon']" :size="24" />
                    </span>

                    <h3 class="relative mt-5 text-base font-bold text-ink">{{ $step['title'] }}</h3>
                    <p class="relative mt-2 text-sm leading-relaxed text-muted">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
