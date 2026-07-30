@extends('layouts.writer-dashboard')

@section('title', __('writer.articles.edit'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('writer.articles.edit') }}</h1>

    @if ($article->isRejected() && $article->rejection_reason)
        <div class="mt-4 max-w-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-medium">{{ __('writer.articles.rejection_reason_label') }}</p>
            <p class="mt-1">{{ $article->rejection_reason }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('writer.dashboard.articles.update', $article) }}" enctype="multipart/form-data" class="mt-6 max-w-xl space-y-5" novalidate>
        @csrf
        @method('PUT')

        <x-article-form :article="$article" :languages="$languages" />

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('writer.articles.save') }}
        </button>
    </form>
@endsection
