@extends('layouts.app')

@section('title', 'Email preview')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16">
        <h1 class="font-heading text-2xl font-bold text-ink">Email preview</h1>
        <p class="mt-2 text-sm text-muted">
            Dev-only. Renders each transactional email with realistic stub data - nothing is sent, nothing is written to the database.
        </p>

        <ul class="mt-8 divide-y divide-border-muted rounded-2xl border border-border-muted bg-white">
            @foreach ($templates as $slug => $label)
                <li>
                    <a
                        href="{{ route('email-preview.show', ['locale' => app()->getLocale(), 'template' => $slug]) }}"
                        target="_blank"
                        class="flex items-center justify-between px-6 py-4 text-sm font-medium text-ink transition hover:bg-placeholder/40"
                    >
                        {{ $label }}
                        <span aria-hidden="true" class="text-muted">&rarr;</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
