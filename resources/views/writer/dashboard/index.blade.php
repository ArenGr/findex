@extends('layouts.writer-dashboard')

@section('title', $writer->name . ' — Findex')

@section('content')
    <h1 class="font-heading text-2xl font-bold text-ink">{{ $writer->name }}</h1>

    @if ($writer->is_active)
        <p class="mt-4 text-sm text-muted">
            {{ __('writer.dashboard_placeholder') }}
        </p>
    @endif
@endsection
