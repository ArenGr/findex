@extends('layouts.app')

@section('title', $metaTitle)
@section('description', $metaDescription)

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="mx-auto max-w-2xl text-center">
            <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ $heading }}</h1>
            <p class="mt-3 text-sm leading-relaxed text-muted">{{ $subtitle }}</p>
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <div class="rounded-2xl border border-primary/20 bg-white px-6 py-4 text-center shadow-sm">
                <p class="font-heading text-2xl font-bold text-primary">{{ $organizations->total() }}</p>
                <p class="mt-1 text-xs font-medium text-muted">{{ $statLabel }}</p>
            </div>
        </div>

        @if ($ctaRoute)
            <div class="mt-8 text-center">
                <a
                    href="{{ $ctaRoute }}"
                    class="inline-block bg-primary px-8 py-3 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md"
                >
                    {{ $ctaLabel }}
                </a>
            </div>
        @endif

        <form method="GET" class="mx-auto mt-10 flex max-w-md gap-2">
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('organizations.search_placeholder') }}"
                class="block w-full rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none"
            >
            <button type="submit" class="shrink-0 bg-primary px-5 py-2.5 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md">
                {{ __('organizations.search_button') }}
            </button>
        </form>

        <div class="mt-6 divide-y divide-placeholder overflow-hidden rounded-2xl border border-placeholder bg-white shadow-sm">
            @forelse ($organizations as $organization)
                <x-organization-row :organization="$organization" :show-compare="$showCompare" />
            @empty
                <p class="px-5 py-12 text-center text-sm text-muted">{{ __('organizations.no_organizations') }}</p>
            @endforelse
        </div>

        <div class="mt-10">
            {{ $organizations->links() }}
        </div>
    </section>
@endsection
