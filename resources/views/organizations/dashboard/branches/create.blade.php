@extends('layouts.dashboard')

@section('title', __('org.branches.add'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.branches.add') }}</h1>

    <form method="POST" action="{{ route('org.dashboard.branches.store') }}" class="mt-6 max-w-xl space-y-5">
        @csrf

        <x-branch-form />

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.branches.save') }}
        </button>
    </form>
@endsection
