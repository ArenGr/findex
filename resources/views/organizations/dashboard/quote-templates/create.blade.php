@extends('layouts.dashboard')

@section('title', __('org.quote_templates.add'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.quote_templates.add') }}</h1>

    <form method="POST" action="{{ route('org.dashboard.quote-templates.store') }}" class="mt-6 max-w-xl space-y-5" novalidate>
        @csrf

        <x-quote-template-form :destinations="$destinations" />

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.quote_templates.save') }}
        </button>
    </form>
@endsection
