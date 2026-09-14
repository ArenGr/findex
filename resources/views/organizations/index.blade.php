@extends('layouts.app')

@section('title', __('organizations.directory_heading') . ' — Findex')

@section('content')
    {{-- The directory is the same two steps as every other page: say what you
         are looking for in the hero, compare what comes back below it. --}}
    <x-page-hero :title="__('organizations.directory_heading')" :subtitle="__('organizations.directory_subtitle')">
        <x-slot:panel>
            <form method="GET" action="{{ route('organizations.index') }}" class="flex flex-wrap items-center gap-3">
                @if ($activeType)
                    <input type="hidden" name="type" value="{{ $activeType }}">
                @endif

                <label for="organizations-search" class="sr-only">{{ __('organizations.search_placeholder') }}</label>
                <input
                    id="organizations-search"
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    placeholder="{{ __('organizations.search_placeholder') }}"
                    class="field min-w-0 flex-1"
                >
                <button type="submit" class="btn btn-primary shrink-0">
                    {{ __('organizations.search_button') }}
                </button>
            </form>
        </x-slot:panel>

        <x-slot:facts>
            <li>{{ trans_choice('compare_ui.count', $organizations->total(), ['count' => number_format($organizations->total())]) }}</li>
            <li>{{ trans_choice('compare_ui.categories', count($types), ['count' => count($types)]) }}</li>
        </x-slot:facts>
    </x-page-hero>

    <x-results :count="$organizations->total()">
        {{-- One control per filter, stating its own answer. --}}
        <x-slot:filters>
            <p class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('organizations.stat_type') }}</p>
            <div class="mt-3 flex flex-wrap gap-2 lg:flex-col lg:items-start">
                @foreach (array_merge([null], $types) as $type)
                    <a
                        href="{{ route('organizations.index', array_filter(['type' => $type, 'q' => $search])) }}"
                        @class([
                            'rounded-xl px-4 py-2 text-sm font-medium transition lg:w-full',
                            'bg-primary text-white' => $activeType === $type,
                            'text-muted hover:bg-surface-alt hover:text-ink' => $activeType !== $type,
                        ])
                    >
                        {{ $type === null ? __('organizations.filter_all_types') : __('organizations.types.' . $type) }}
                    </a>
                @endforeach
            </div>
        </x-slot:filters>

        <div class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">
            @forelse ($organizations as $organization)
                <x-organization-row :organization="$organization" :show-compare="true" />
            @empty
                <p class="px-5 py-12 text-center text-sm text-muted">{{ __('organizations.no_organizations') }}</p>
            @endforelse
        </div>

        <x-slot:pagination>
            {{ $organizations->links() }}
        </x-slot:pagination>
    </x-results>

    <x-ad-slot placement="organizations_index" />
@endsection
