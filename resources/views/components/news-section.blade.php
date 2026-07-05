@php
    $articles = array_fill(0, 4, [
        'title' => 'Lorem Ipsum Dolor Sit Amet Consectetur',
        'excerpt' => 'Lorem ipsum dolor sit amet consectetur',
        'author' => 'Luiza Araqelyan',
        'date' => now()->subDays(7)->translatedFormat('d F, Y'),
    ]);
@endphp

<section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
    <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('news.heading') }}</h2>
    <p class="mt-2 max-w-2xl text-sm text-muted">
        {{ __('news.subtitle') }}
    </p>

    <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($articles as $article)
            <article class="border border-placeholder p-5">
                <h3 class="font-semibold text-ink">{{ $article['title'] }}</h3>
                <p class="mt-1 text-xs text-muted">{{ $article['excerpt'] }}</p>

                <div class="mt-6 flex items-center gap-2">
                    <span class="h-8 w-8 rounded-full bg-placeholder"></span>
                    <span class="text-xs text-body-text">{{ $article['author'] }}</span>
                </div>

                <p class="mt-3 text-xs text-subtle">{{ $article['date'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="mt-10 text-center">
        <a href="#" class="inline-block bg-primary px-8 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('common.learn_more') }}
        </a>
    </div>
</section>
