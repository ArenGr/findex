@extends('layouts.dashboard')

@section('title', __('org.reports.request'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.reports.request') }}</h1>

    <form method="POST" action="{{ route('org.dashboard.reports.store') }}" class="mt-6 max-w-xl space-y-5">
        @csrf

        @if ($branches->isNotEmpty())
            <div>
                <label for="branch_id" class="block text-sm font-medium text-ink">{{ __('org.reports.branch') }}</label>
                <select
                    name="branch_id"
                    id="branch_id"
                    class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                >
                    <option value="">{{ __('org.reports.all_branches') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <x-form-input name="period_from" type="date" :label="__('org.reports.period_from')" />
        <x-form-input name="period_to" type="date" :label="__('org.reports.period_to')" />

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.reports.request_button') }}
        </button>
    </form>
@endsection
