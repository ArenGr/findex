@extends('layouts.dashboard')

@section('title', __('org.branches.add'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.branches.add') }}</h1>

    <form method="POST" action="{{ route('org.dashboard.branches.store') }}" class="mt-6 max-w-xl space-y-5" novalidate>
        @csrf

        <x-branch-form />

        <button type="submit" class="btn btn-primary">
            {{ __('org.branches.save') }}
        </button>
    </form>
@endsection
