@extends('layouts.app')

@section('title', $article->title . ' — Findex')
@section('description', $article->summary())

@section('content')
    @php
        $paragraphs = preg_split('/\n\s*\n/', trim($article->body)) ?: [];
    @endphp

    {{--
        Outer article matches the home page's max-w-7xl. The actual body
        copy (title, excerpt, byline, paragraphs) stays in a narrower
        inner wrapper - see faq.blade.php for the same reasoning, doubly
        true for a wall of paragraph text at the new, larger text-base
        size (resources/css/app.css). The related-articles grid at the
        bottom is genuine multi-column content, so it uses the full outer
        width instead.
    --}}
    <article class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('home') }}" class="group inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current transition-transform duration-300 group-hover:-translate-x-1">
                    <path d="M11 5 4 12l7 7M4 12h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                {{ __('articles.back_to_home') }}
            </a>

            <h1 class="mt-6 font-heading text-3xl leading-tight font-bold text-ink lg:text-4xl">{{ $article->title }}</h1>

            @if ($article->excerpt)
                <p class="mt-4 text-lg leading-relaxed text-muted">{{ $article->excerpt }}</p>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3 border-y border-placeholder py-4 text-sm text-muted">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary font-heading text-xs font-bold text-white ring-2 ring-primary/20">
                    {{ Str::of($article->writer->name)->substr(0, 2)->upper() }}
                </span>
                <span>{{ __('articles.by') }} <span class="font-medium text-ink">{{ $article->writer->name }}</span></span>

                @if ($article->reviewedBy)
                    <span class="text-subtle">&middot;</span>
                    <span>{{ __('articles.edited_by', ['name' => $article->reviewedBy->name]) }}</span>
                @endif

                <span class="text-subtle">&middot;</span>
                <span>{{ $article->published_at->translatedFormat('d F, Y') }}</span>
            </div>

            @if ($article->featured_image_url)
                <div class="mt-10 overflow-hidden rounded-2xl shadow-sm">
                    <img src="{{ $article->featured_image_url }}" alt="" class="h-auto w-full object-cover">
                </div>
            @endif

            <div class="mt-10 space-y-6 text-[17px] leading-loose text-body-text">
                @foreach ($paragraphs as $index => $paragraph)
                    <p>{{ $paragraph }}</p>

                    @if (($index + 1) % 3 === 0 && !$loop->last)
                        <div class="flex justify-center py-2">
                            <x-ad-slot placement="article_body" />
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="mt-16 border-t border-placeholder pt-10">
                <h2 class="font-heading text-xl font-semibold text-ink">{{ __('articles.related_heading') }}</h2>

                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    @foreach ($related as $relatedArticle)
                        <a
                            href="{{ route('articles.show', $relatedArticle) }}"
                            class="group block overflow-hidden rounded-2xl border border-placeholder bg-white shadow-sm transition-colors duration-300 hover:border-primary"
                        >
                            @if ($relatedArticle->featured_image_url)
                                <div class="p-3">
                                    <div class="h-32 w-full overflow-hidden rounded-xl">
                                        <img
                                            src="{{ $relatedArticle->featured_image_url }}"
                                            alt=""
                                            loading="lazy"
                                            class="h-full w-full object-cover"
                                        >
                                    </div>
                                </div>
                            @endif

                            <div class="px-4 pb-4 {{ $relatedArticle->featured_image_url ? '' : 'pt-4' }}">
                                <h3 class="min-h-[2.5rem] line-clamp-2 text-sm font-semibold text-ink">{{ $relatedArticle->title }}</h3>

                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <p class="text-xs text-subtle">{{ $relatedArticle->published_at->translatedFormat('d F, Y') }}</p>

                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-primary bg-primary text-white transition-colors duration-300 group-hover:bg-white group-hover:text-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-2.5 w-2.5 fill-none stroke-current">
                                            <path d="M7 17 17 7M9 7h8v8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </article>
@endsection
