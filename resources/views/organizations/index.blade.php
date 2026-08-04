@extends('layouts.app')

@section('title', __('organizations.directory_heading') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="lg:flex lg:items-start lg:gap-10">
            <div class="min-w-0 flex-1">
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('organizations.directory_heading') }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('organizations.directory_subtitle') }}</p>

                <form method="GET" action="{{ route('organizations.index') }}" class="mt-8 flex gap-2">
                    @if ($activeType)
                        <input type="hidden" name="type" value="{{ $activeType }}">
                    @endif
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="{{ __('organizations.search_placeholder') }}"
                        class="block w-full max-w-sm rounded-lg border border-border-muted px-3 py-2.5 text-sm text-ink focus:border-primary focus:outline-none"
                    >
                    <button type="submit" class="shrink-0 bg-primary px-5 py-2.5 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md">
                        {{ __('organizations.search_button') }}
                    </button>
                </form>

                {{-- Type filter --}}
                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        href="{{ route('organizations.index', array_filter(['q' => $search])) }}"
                        class="rounded-full px-4 py-2 text-xs font-medium transition {{ $activeType === null ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                    >
                        {{ __('organizations.filter_all_types') }}
                    </a>
                    @foreach ($types as $type)
                        <a
                            href="{{ route('organizations.index', array_filter(['type' => $type, 'q' => $search])) }}"
                            class="rounded-full px-4 py-2 text-xs font-medium transition {{ $activeType === $type ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                        >
                            {{ __('organizations.types.' . $type) }}
                        </a>
                    @endforeach
                </div>

                {{-- Organizations list --}}
                <div class="mt-10 divide-y divide-placeholder overflow-hidden rounded-2xl border border-placeholder bg-white shadow-sm">
                    @forelse ($organizations as $organization)
                        <x-organization-row :organization="$organization" :show-compare="true" />
                    @empty
                        <p class="px-5 py-12 text-center text-sm text-muted">{{ __('organizations.no_organizations') }}</p>
                    @endforelse
                </div>

                <div class="mt-10">
                    {{ $organizations->links() }}
                </div>
            </div>

            <x-ad-slot placement="organizations_index" />
        </div>
    </section>
@endsection
