@extends('layouts.app')

@section('title', __('organizations.directory_heading') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="lg:flex lg:items-start lg:gap-10">
            <div class="min-w-0 flex-1">
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('organizations.directory_heading') }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('organizations.directory_subtitle') }}</p>

                {{-- Type filter --}}
                <div class="mt-8 flex flex-wrap gap-2">
                    <a
                        href="{{ route('organizations.index') }}"
                        class="rounded-full px-4 py-2 text-xs font-medium transition {{ $activeType === null ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                    >
                        {{ __('organizations.filter_all_types') }}
                    </a>
                    @foreach ($types as $type)
                        <a
                            href="{{ route('organizations.index', ['type' => $type]) }}"
                            class="rounded-full px-4 py-2 text-xs font-medium transition {{ $activeType === $type ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink' }}"
                        >
                            {{ __('organizations.types.' . $type) }}
                        </a>
                    @endforeach
                </div>

                {{-- Organizations grid --}}
                <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($organizations as $organization)
                        <div class="border border-placeholder p-5 transition hover:border-primary">
                            <a href="{{ route('organizations.show', $organization) }}" class="block">
                                <div class="flex items-center gap-3">
                                    @if ($organization->logo)
                                        <img src="{{ $organization->logo }}" alt="" class="h-12 w-12 shrink-0 rounded-full object-contain">
                                    @else
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-sm font-bold text-white">
                                            {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                                        </div>
                                    @endif

                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-ink">{{ $organization->name }}</p>
                                        <p class="text-xs text-subtle">{{ __('organizations.types.' . $organization->type) }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center gap-2">
                                    <x-star-rating :rating="$organization->reviews_avg_rating ?? 0" />
                                    <span class="text-xs text-muted">
                                        @if ($organization->reviews_count > 0)
                                            {{ number_format($organization->reviews_avg_rating, 1) }}
                                            ({{ trans_choice('organizations.reviews_count', $organization->reviews_count, ['count' => $organization->reviews_count]) }})
                                        @else
                                            {{ __('organizations.unrated') }}
                                        @endif
                                    </span>
                                </div>

                                <div class="mt-3">
                                    <x-organization-badges :organization="$organization" />
                                </div>
                            </a>

                            <x-compare-toggle :organization="$organization" class="mt-4 w-full" />
                        </div>
                    @empty
                        <p class="col-span-full py-12 text-center text-sm text-muted">{{ __('organizations.no_organizations') }}</p>
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
