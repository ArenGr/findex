@extends('layouts.app')

@section('title', __('visa.respond.heading') . ' — Findex')

@php
    $labelClass = 'block text-xs font-semibold tracking-wider text-muted uppercase';
    $fieldLabel = 'block text-[13px] font-semibold text-ink';
    $panel = 'rounded-3xl border border-border bg-surface p-5 sm:p-6';
    $error = 'mt-1.5 text-xs text-accent-red';
@endphp

@section('content')
    <x-vertical-hero
        icon="ticket"
        :eyebrow="__('visa.request.eyebrow')"
        :title="__('visa.respond.heading')"
        :subtitle="$response ? __('visa.respond.intro') : null"
    />

    <section class="site-container pt-9 pb-12">
        @if ($response === null)
            <div class="rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
                <p class="font-heading text-base font-bold text-ink">{{ __('visa.respond.unknown_heading') }}</p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">{{ __('visa.respond.unknown_body') }}</p>
            </div>
        @else
            @php $request = $response->visaRequest; @endphp

            <div class="{{ $panel }}">
                <dl class="flex flex-wrap gap-x-10 gap-y-4">
                    <div class="min-w-0">
                        <dt class="{{ $labelClass }}">{{ __('visa.respond.destination') }}</dt>
                        <dd class="mt-1 text-base font-semibold text-ink">{{ $request->destination_label }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="{{ $labelClass }}">{{ __('visa.respond.dates') }}</dt>
                        <dd class="mt-1 text-base font-semibold text-ink">
                            {{ $request->travel_from->translatedFormat('d M Y') }} – {{ $request->travel_to->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="{{ $labelClass }}">{{ __('visa.respond.applicants') }}</dt>
                        <dd class="mt-1 text-base font-semibold text-ink">{{ $request->applicants }}</dd>
                    </div>
                </dl>
            </div>

            @if ($response->is_declined)
                <div class="mt-6 rounded-2xl border border-border bg-surface-alt px-6 py-10 text-center">
                    <p class="font-heading text-base font-bold text-ink">{{ __('visa.respond.declined_heading') }}</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">{{ __('visa.respond.declined_body') }}</p>
                </div>
            @elseif (! $request->is_open)
                <div class="mt-6 rounded-2xl border border-border bg-surface-alt px-6 py-10 text-center">
                    <p class="font-heading text-base font-bold text-ink">{{ __('visa.respond.closed_heading') }}</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">{{ __('visa.respond.closed_body') }}</p>
                </div>
            @else
                @if ($response->has_replied)
                    <div class="mt-6 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary-dark">
                        <span class="font-semibold">{{ __('visa.respond.sent_heading') }}</span> — {{ __('visa.respond.sent_body') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('visa.respond.store', ['token' => $response->response_token]) }}" class="{{ $panel }} mt-6" novalidate>
                    @csrf

                    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        <div class="min-w-0">
                            <label for="price_amount" class="{{ $fieldLabel }}">{{ __('visa.respond.price') }}</label>
                            <input type="number" step="0.01" min="0" name="price_amount" id="price_amount"
                                value="{{ old('price_amount', $response->price_amount) }}" required class="field mt-2">
                            @error('price_amount')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="price_currency" class="{{ $fieldLabel }}">{{ __('visa.respond.currency') }}</label>
                            <select name="price_currency" id="price_currency" required class="field mt-2">
                                @foreach (\App\Models\VisaResponse::CURRENCIES as $currency)
                                    <option value="{{ $currency }}" @selected(old('price_currency', $response->price_currency ?? 'AMD') === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                            @error('price_currency')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="processing_days" class="{{ $fieldLabel }}">{{ __('visa.respond.processing_days') }}</label>
                            <input type="number" min="1" max="{{ \App\Models\VisaResponse::MAX_PROCESSING_DAYS }}" name="processing_days" id="processing_days"
                                value="{{ old('processing_days', $response->processing_days) }}" class="field mt-2">
                            @error('processing_days')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="valid_until" class="{{ $fieldLabel }}">{{ __('visa.respond.valid_until') }}</label>
                            <input type="date" name="valid_until" id="valid_until"
                                value="{{ old('valid_until', $response->valid_until?->format('Y-m-d')) }}" class="field mt-2">
                            @error('valid_until')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="mt-5">
                        <label for="reply_text" class="{{ $fieldLabel }}">{{ __('visa.respond.reply_text') }}</label>
                        <textarea name="reply_text" id="reply_text" rows="4" placeholder="{{ __('visa.respond.reply_placeholder') }}"
                            class="field mt-2 min-h-[8rem]">{{ old('reply_text', $response->reply_text) }}</textarea>
                        @error('reply_text')<p class="{{ $error }}">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-5">
                        <button type="submit" name="decline" value="1" class="btn btn-secondary">
                            {{ __('visa.respond.decline') }}
                        </button>

                        <button type="submit" class="btn btn-primary">
                            {{ $response->has_replied ? __('visa.respond.update') : __('visa.respond.submit') }}
                            <x-lucide name="arrow-right" :size="18" />
                        </button>
                    </div>
                </form>
            @endif
        @endif
    </section>
@endsection
