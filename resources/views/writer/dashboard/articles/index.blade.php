@extends('layouts.writer-dashboard')

@section('title', __('writer.articles.title'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-heading text-xl font-semibold text-ink">{{ __('writer.articles.title') }}</h1>
        <a href="{{ route('writer.dashboard.articles.create') }}" class="bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('writer.articles.add') }}
        </a>
    </div>
    <p class="mt-1 text-sm text-muted">{{ __('writer.articles.subtitle') }}</p>

    <div class="mt-6 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($articles as $article)
            <div class="flex items-center justify-between py-4 text-sm">
                <div>
                    <p class="font-medium text-ink">{{ $article->title }}</p>
                    <p class="text-xs text-muted">
                        {{ __('writer.articles.' . ($article->isDraft() ? 'status_draft' : 'status_submitted')) }}
                        &middot; {{ strtoupper($article->language) }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    @if ($article->isDraft())
                        <a href="{{ route('writer.dashboard.articles.edit', $article) }}" class="font-medium text-primary hover:underline">
                            {{ __('writer.articles.edit') }}
                        </a>
                        <form method="POST" action="{{ route('writer.dashboard.articles.submit', $article) }}" onsubmit="return confirm('{{ __('writer.articles.submit') }}?')">
                            @csrf
                            <button type="submit" class="font-medium text-primary hover:underline">{{ __('writer.articles.submit') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('writer.dashboard.articles.destroy', $article) }}" onsubmit="return confirm('{{ __('writer.articles.delete') }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-medium text-red-600 hover:underline">{{ __('writer.articles.delete') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('writer.articles.no_articles') }}</p>
        @endforelse
    </div>
@endsection
