@php
    $articles = \App\Models\Article::published()
        ->where('language', app()->getLocale())
        ->with('writer')
        ->latest('published_at')
        ->take(4)
        ->get();
@endphp

@if ($articles->isNotEmpty())
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('news.heading') }}</h2>
        <p class="mt-2 max-w-2xl text-sm text-muted">
            {{ __('news.subtitle') }}
        </p>

        <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($articles as $article)
                <a
                    href="{{ route('articles.show', $article) }}"
                    class="group block overflow-hidden rounded-2xl border border-placeholder bg-white shadow-sm transition-colors duration-300 hover:border-primary"
                >
                    @if ($article->featured_image_url)
                        <div class="p-3">
                            <div class="h-40 w-full overflow-hidden rounded-xl">
                                <img
                                    src="{{ $article->featured_image_url }}"
                                    alt=""
                                    loading="lazy"
                                    class="h-full w-full object-cover"
                                >
                            </div>
                        </div>
                    @endif

                    <div class="px-5 pb-5 {{ $article->featured_image_url ? '' : 'pt-5' }}">
                        <h3 class="line-clamp-2 min-h-[3rem] font-semibold text-ink">{{ $article->title }}</h3>
                        <p class="mt-2 line-clamp-2 text-sm text-muted">{{ $article->summary() }}</p>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <p class="text-xs text-subtle">
                                {{ __('articles.by') }} {{ $article->writer->name }} &middot; {{ $article->published_at->translatedFormat('d F, Y') }}
                            </p>

                            <span class="flex shrink-0 items-center gap-1.5 text-xs font-semibold text-primary">
                                {{ __('common.read_more') }}
                                <span class="flex h-6 w-6 items-center justify-center rounded-full border border-primary bg-primary text-white transition-colors duration-300 group-hover:bg-white group-hover:text-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3 w-3 fill-none stroke-current">
                                        <path d="M7 17 17 7M9 7h8v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
