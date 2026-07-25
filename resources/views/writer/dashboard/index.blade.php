{{-- NOT layouts.dashboard - that layout is hardcoded to the 'organization'
     guard/nav (auth('organization'), org.dashboard.* routes, org.logout
     form) and isn't reusable here. This is a bare placeholder until a real
     writer dashboard (article drafting/publishing) exists. --}}
@extends('layouts.app')

@section('title', $writer->name . ' — Findex')

@section('content')
    <section class="mx-auto max-w-2xl px-6 py-16 lg:py-24">
        <h1 class="font-heading text-2xl font-bold text-ink">{{ $writer->name }}</h1>

        @unless ($writer->is_active)
            <p class="mt-4 rounded-xl border border-placeholder bg-placeholder/10 px-4 py-3 text-sm text-muted">
                {{ __('writer.pending_approval') }}
            </p>
        @else
            <p class="mt-4 text-sm text-muted">
                {{ __('writer.dashboard_placeholder') }}
            </p>
        @endunless
    </section>
@endsection
