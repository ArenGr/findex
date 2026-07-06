@extends('layouts.dashboard')

@section('title', __('org.branches.title'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.branches.title') }}</h1>
        <a href="{{ route('org.dashboard.branches.create') }}" class="bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.branches.add') }}
        </a>
    </div>

    <div class="mt-6 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($branches as $branch)
            <div class="flex items-center justify-between py-4 text-sm">
                <div>
                    <p class="font-medium text-ink">
                        {{ $branch->name }}
                        @unless ($branch->is_active)
                            <span class="ml-2 text-xs text-subtle">({{ __('org.branches.inactive') }})</span>
                        @endunless
                    </p>
                    <p class="text-xs text-muted">{{ collect([$branch->address, $branch->city])->filter()->implode(', ') }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('org.dashboard.branches.edit', $branch) }}" class="font-medium text-primary hover:underline">
                        {{ __('org.branches.edit') }}
                    </a>
                    <form method="POST" action="{{ route('org.dashboard.branches.destroy', $branch) }}" onsubmit="return confirm('{{ __('org.branches.delete') }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-medium text-red-600 hover:underline">{{ __('org.branches.delete') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('org.branches.no_branches') }}</p>
        @endforelse
    </div>
@endsection
