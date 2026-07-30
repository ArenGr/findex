@extends('layouts.writer-dashboard')

@section('title', __('writer.articles.add'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('writer.articles.add') }}</h1>

    <form method="POST" action="{{ route('writer.dashboard.articles.store') }}" enctype="multipart/form-data" class="mt-6 max-w-xl space-y-5" novalidate>
        @csrf

        <x-article-form :languages="$languages" />

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('writer.articles.save') }}
        </button>
    </form>
@endsection
