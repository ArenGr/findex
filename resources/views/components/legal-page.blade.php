@props(['title', 'updated', 'sections'])

<section class="mx-auto max-w-3xl px-6 py-16 lg:px-10">
    <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ $title }}</h1>
    <p class="mt-2 text-xs text-subtle">{{ __('legal.last_updated', ['date' => $updated->translatedFormat('d F Y')]) }}</p>

    <div class="mt-10 space-y-8">
        @foreach ($sections as $section)
            <div>
                <h2 class="font-heading text-base font-semibold text-ink">{{ $section['title'] }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-body-text">{{ $section['body'] }}</p>
            </div>
        @endforeach
    </div>

    <p class="mt-12 border-t border-placeholder pt-6 text-sm text-muted">
        {!! __('legal.contact_prompt', ['email' => '<a href="mailto:legal@findex.am" class="text-primary hover:underline">legal@findex.am</a>']) !!}
    </p>
</section>
