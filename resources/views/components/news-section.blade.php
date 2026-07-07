@php
    $newsFeed = app(\App\Services\News\ArmenpressNewsFeed::class);
    $articles = $newsFeed->latest(app()->getLocale());
@endphp

@if (!empty($articles))
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('news.heading') }}</h2>
        <p class="mt-2 max-w-2xl text-sm text-muted">
            {{ __('news.subtitle') }}
        </p>

        <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($articles as $article)
                <a href="{{ $article['url'] }}" target="_blank" rel="noopener" class="block border border-placeholder transition hover:border-primary">
                    @if ($article['image'])
                        <img
                            src="{{ $article['image'] }}"
                            alt=""
                            loading="lazy"
                            class="h-36 w-full object-cover"
                        >
                    @endif

                    <div class="p-5">
                        @if ($article['category'])
                            <span class="text-xs font-semibold tracking-wide text-primary uppercase">{{ $article['category'] }}</span>
                        @endif

                        <h3 class="mt-2 font-semibold text-ink">{{ $article['title'] }}</h3>

                        <p class="mt-3 text-xs text-subtle">
                            Armenpress &middot; {{ $article['published_at']->translatedFormat('d F, Y') }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-10 text-center">
            <a
                href="{{ $newsFeed->categoryUrl(app()->getLocale()) }}"
                target="_blank"
                rel="noopener"
                class="inline-block bg-primary px-8 py-3 text-sm font-medium text-white hover:bg-primary-dark"
            >
                {{ __('common.learn_more') }}
            </a>
        </div>
    </section>
@endif
