@extends('layouts.app')

@section('title', __('api.your_keys') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-4xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink">{{ __('api.your_keys') }}</h1>

        {{-- The only moment this key exists in a form anyone can read. It is
        flashed, never stored, and the page says so plainly. --}}
        @if (session('new_api_key'))
            <div class="mt-6 rounded-2xl border-2 border-primary/40 bg-primary/5 p-5">
                <p class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('api.shown_once') }}</p>
                <p class="mt-2 font-mono text-sm break-all text-ink select-all">{{ session('new_api_key') }}</p>
                <p class="mt-2 text-sm break-words text-muted">{{ __('api.copy_now') }}</p>
            </div>
        @endif

        @if (session('status') === 'api-key-revoked')
            <p class="mt-6 rounded-xl border border-placeholder bg-placeholder/20 px-4 py-3 text-sm break-words text-ink">{{ __('api.revoked') }}</p>
        @endif

        <form method="POST" action="{{ route('api.keys.store') }}" class="mt-8 flex flex-wrap items-end gap-3 rounded-2xl border border-placeholder bg-white p-5">
            @csrf
            <input type="hidden" name="plan" value="free">

            <div class="min-w-0 flex-1">
                <label for="name" class="block text-xs font-semibold tracking-wider text-muted uppercase">{{ __('api.key_name') }}</label>
                <input
                    type="text" name="name" id="name" required maxlength="60"
                    value="{{ old('name') }}"
                    class="mt-2 w-full min-w-0 rounded-lg border border-border-muted bg-white px-4 py-2.5 text-sm text-ink focus:border-primary focus:outline-none"
                >
                @error('name')
                    <p class="mt-1 text-xs break-words text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold break-words text-white transition hover:bg-primary-dark">
                {{ __('api.create_key') }}
            </button>
        </form>

        @if ($keys->isEmpty())
            <p class="mt-8 text-sm break-words text-muted">{{ __('api.no_keys') }}</p>
        @else
            <ul class="mt-8 space-y-3">
                @foreach ($keys as $key)
                    <li class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-placeholder bg-white p-5">
                        <div class="min-w-0">
                            <p class="font-medium break-words text-ink">{{ $key->name }}</p>
                            {{-- The prefix only: enough to tell two keys apart,
                            not enough to use. --}}
                            <p class="mt-0.5 font-mono text-xs break-all text-muted">{{ $key->prefix }}&hellip;</p>
                            <p class="mt-1 text-xs break-words text-muted">
                                {{ $plans[$key->plan]['label'] ?? $key->plan }}
                                &middot; {{ __('api.this_month') }}: <span class="tabular-nums">{{ number_format((int) $key->requests_this_month) }}</span>
                            </p>
                        </div>

                        <form method="POST" action="{{ route('api.keys.destroy', $key) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-placeholder px-4 py-2 text-sm font-medium break-words text-ink transition hover:bg-placeholder/25">
                                {{ __('api.revoke') }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
