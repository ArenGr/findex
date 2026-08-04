@extends('layouts.app')

@section('title', __('exchange_quotes.respond.title') . ' — Findex')

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        @if (!$response)
            <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                <h1 class="font-heading text-xl font-semibold text-ink">{{ __('exchange_quotes.respond.not_found_heading') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ __('exchange_quotes.respond.not_found_body') }}</p>
            </div>
        @else
            @php $request = $response->exchangeQuoteRequest; @endphp

            @if ($response->has_replied)
                <div class="rounded-2xl border border-primary/30 bg-primary/5 p-6 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('exchange_quotes.respond.success_heading') }}</h1>
                    <p class="mt-2 text-sm text-body-text">{{ __('exchange_quotes.respond.success_body') }}</p>
                </div>

                <div class="mt-8 rounded-2xl border border-placeholder p-6">
                    <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('exchange_quotes.respond.offered_rate_label') }}</p>
                    <p class="mt-1 font-heading text-2xl font-bold text-primary">
                        {{ number_format((float) $response->offered_rate, 2) }} {{ __('exchange_quotes.request.amd') }}
                    </p>

                    @if ($response->reply_text)
                        <p class="mt-4 rounded-xl bg-placeholder/20 px-4 py-3 text-sm text-ink">{{ $response->reply_text }}</p>
                    @endif
                </div>
            @elseif ($response->is_declined)
                <div class="rounded-2xl border border-placeholder p-8 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('exchange_quotes.respond.declined_heading') }}</h1>
                    <p class="mt-2 text-sm text-muted">{{ __('exchange_quotes.respond.declined_body') }}</p>
                </div>
            @elseif (!$request->is_open)
                <div class="rounded-2xl border border-placeholder p-8 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('exchange_quotes.respond.expired_heading') }}</h1>
                    <p class="mt-2 text-sm text-muted">{{ __('exchange_quotes.respond.expired_body') }}</p>
                </div>
            @else
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('exchange_quotes.respond.heading') }}</h1>

                {{-- Customer's request --}}
                <div class="mt-6 rounded-2xl border border-placeholder p-5 shadow-sm">
                    <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('exchange_quotes.respond.customer_request_heading') }}</p>

                    <div class="mt-3 flex items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">💱</span>
                        <div class="min-w-0">
                            <p class="font-heading font-semibold text-ink">
                                {{ number_format((float) $request->amount, 2) }} {{ $request->currency->code }}
                                &middot;
                                {{ __('exchange_quotes.request.direction_' . $request->rate_field, ['currency' => $request->currency->code], $request->locale) }}
                            </p>
                        </div>
                    </div>

                    @if ($request->notes)
                        <p class="mt-4 rounded-xl bg-placeholder/20 px-4 py-3 text-sm text-ink">{{ $request->notes }}</p>
                    @endif
                </div>

                {{-- Offer form --}}
                <form
                    method="POST"
                    action="{{ route('exchange.respond.store', ['locale' => app()->getLocale(), 'token' => $response->response_token]) }}"
                    class="mt-8 space-y-5 rounded-2xl border border-placeholder p-6 shadow-sm"
                    novalidate
                >
                    @csrf

                    <div>
                        <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('exchange_quotes.respond.posted_rate_label') }}</p>
                        <p class="mt-1 font-heading text-xl font-bold text-ink">{{ number_format((float) $response->posted_rate, 2) }} {{ __('exchange_quotes.request.amd') }}</p>
                    </div>

                    <div>
                        <label for="offered_rate" class="block text-sm font-medium text-ink">{{ __('exchange_quotes.respond.offered_rate_label') }}</label>
                        <p class="text-xs text-muted">{{ __('exchange_quotes.respond.offered_rate_hint') }}</p>
                        <input
                            type="number" step="0.01" min="{{ $response->posted_rate }}" required
                            name="offered_rate" id="offered_rate"
                            value="{{ old('offered_rate', $response->posted_rate) }}"
                            class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('offered_rate') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                        >
                        @error('offered_rate')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reply_text" class="block text-sm font-medium text-ink">{{ __('exchange_quotes.respond.reply_label') }}</label>
                        <textarea
                            name="reply_text"
                            id="reply_text"
                            rows="2"
                            placeholder="{{ __('exchange_quotes.respond.reply_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        >{{ old('reply_text') }}</textarea>
                    </div>

                    <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark sm:w-auto">
                        {{ __('exchange_quotes.respond.submit_button') }}
                    </button>
                </form>
            @endif
        @endif
    </section>
@endsection
