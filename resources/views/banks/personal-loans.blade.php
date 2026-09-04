@extends('layouts.app')

@section('title', __('offers.categories.personal-loans.title') . ' — Findex')

@section('content')
    <x-page-hero
        :title="__('offers.categories.personal-loans.title')"
        :subtitle="__('offers.categories.personal-loans.body')"
    >
        <x-slot:eyebrow>
            <a href="{{ route('banks.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary transition hover:bg-primary/15">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                {{ __('offers.back_to_all') }}
            </a>
        </x-slot:eyebrow>
        <x-slot:illustration><x-hero-art.banking /></x-slot:illustration>
    </x-page-hero>

    <section class="site-container pt-10 pb-16">

        <div class="mt-8 overflow-hidden rounded-2xl border border-placeholder">
            <x-loan-affordability-calculator />
        </div>
    </section>
@endsection
