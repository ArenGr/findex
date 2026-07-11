@extends('layouts.app')

@section('title', __('tourism.respond.title') . ' — Findex')

@php
    $flags = [
        'AE' => '🇦🇪', 'EG' => '🇪🇬', 'GE' => '🇬🇪', 'GR' => '🇬🇷',
        'TH' => '🇹🇭', 'CY' => '🇨🇾', 'IT' => '🇮🇹', 'FR' => '🇫🇷', 'ES' => '🇪🇸',
    ];
@endphp

@section('content')
    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
        @if (!$response)
            <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.respond.not_found_heading') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ __('tourism.respond.not_found_body') }}</p>
            </div>
        @else
            @php $request = $response->quoteRequest; @endphp

            @if ($response->has_replied)
                <div class="rounded-2xl border border-primary/30 bg-primary/5 p-6 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.respond.success_heading') }}</h1>
                    <p class="mt-2 text-sm text-body-text">{{ __('tourism.respond.success_body') }}</p>
                </div>

                <div class="mt-8 rounded-2xl border border-placeholder p-6">
                    <h2 class="font-heading text-base font-semibold text-ink">{{ __('tourism.respond.your_offer_heading') }}</h2>

                    @if ($response->price_amount)
                        <p class="mt-3 font-heading text-2xl font-bold text-primary">
                            {{ rtrim(rtrim((string) $response->price_amount, '0'), '.') }} {{ $response->price_currency }}
                        </p>
                    @endif

                    <dl class="mt-3 space-y-1 text-sm text-ink">
                        @if ($response->offered_hotel_name)
                            <div><dt class="inline text-subtle">{{ __('tourism.respond.hotel_label') }}:</dt> <dd class="inline">{{ $response->offered_hotel_name }}</dd></div>
                        @endif
                        @if ($response->flight_details)
                            <div><dt class="inline text-subtle">{{ __('tourism.respond.flight_label') }}:</dt> <dd class="inline">{{ $response->flight_details }}</dd></div>
                        @endif
                        @if ($response->inclusions)
                            <div><dt class="inline text-subtle">{{ __('tourism.respond.inclusions_label') }}:</dt> <dd class="inline">{{ $response->inclusions }}</dd></div>
                        @endif
                    </dl>

                    @if ($response->reply_text)
                        <p class="mt-3 rounded-xl bg-placeholder/20 px-4 py-3 text-sm text-ink">{{ $response->reply_text }}</p>
                    @endif

                    @if ($response->attachment_path)
                        <a href="{{ Storage::url($response->attachment_path) }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                            {{ __('tourism.results.attachment_label') }} &darr;
                        </a>
                    @endif
                </div>
            @elseif ($response->is_declined)
                <div class="rounded-2xl border border-placeholder p-8 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.respond.declined_heading') }}</h1>
                    <p class="mt-2 text-sm text-muted">{{ __('tourism.respond.declined_body') }}</p>
                </div>
            @elseif (!$request->is_open)
                <div class="rounded-2xl border border-placeholder p-8 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.respond.expired_heading') }}</h1>
                    <p class="mt-2 text-sm text-muted">{{ __('tourism.respond.expired_body') }}</p>
                </div>
            @else
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.respond.heading') }}</h1>

                {{-- Customer's request --}}
                <div class="mt-6 rounded-2xl border border-placeholder p-5 shadow-sm">
                    <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('tourism.respond.customer_request_heading') }}</p>

                    <div class="mt-3 flex items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">
                            {{ $flags[$request->destination_country] ?? '✈️' }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-heading font-semibold text-ink">
                                {{ __('tourism.results.trip_summary', [
                                    'destination' => __('destinations.' . $request->destination_country),
                                    'check_in' => $request->check_in->translatedFormat('d M'),
                                    'check_out' => $request->check_out->translatedFormat('d M Y'),
                                    'adults' => $request->adults,
                                    'children' => $request->children,
                                ]) }}
                            </p>
                            @if ($request->hotel_name)
                                <p class="text-sm text-muted">{{ $request->hotel_name }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($request->all_inclusive || $request->insurance)
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            @if ($request->all_inclusive)
                                <span class="rounded-full bg-placeholder/40 px-3 py-1 text-ink">{{ __('tourism.request.all_inclusive') }}</span>
                            @endif
                            @if ($request->insurance)
                                <span class="rounded-full bg-placeholder/40 px-3 py-1 text-ink">{{ __('tourism.request.insurance') }}</span>
                            @endif
                        </div>
                    @endif

                    @if ($request->notes)
                        <p class="mt-4 rounded-xl bg-placeholder/20 px-4 py-3 text-sm text-ink">{{ $request->notes }}</p>
                    @endif
                </div>

                {{-- Offer form --}}
                <form
                    method="POST"
                    action="{{ route('tourism.respond.store', ['locale' => app()->getLocale(), 'token' => $response->response_token]) }}"
                    enctype="multipart/form-data"
                    class="mt-8 space-y-5 rounded-2xl border border-placeholder p-6 shadow-sm"
                >
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <x-form-input type="number" step="0.01" min="0" name="price_amount" :label="__('tourism.respond.price_label')" :value="old('price_amount')" required />

                        <div>
                            <label for="price_currency" class="block text-sm font-medium text-ink">{{ __('tourism.respond.currency_label') }}</label>
                            <select
                                name="price_currency"
                                id="price_currency"
                                required
                                class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('price_currency') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                            >
                                <option value="">—</option>
                                @foreach (\App\Models\QuoteResponse::CURRENCIES as $currency)
                                    <option value="{{ $currency }}" @selected(old('price_currency') === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                            @error('price_currency')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <x-form-input
                        name="offered_hotel_name"
                        :label="__('tourism.respond.hotel_label')"
                        :placeholder="__('tourism.respond.hotel_placeholder')"
                        :value="old('offered_hotel_name')"
                    />

                    <div>
                        <label for="flight_details" class="block text-sm font-medium text-ink">{{ __('tourism.respond.flight_label') }}</label>
                        <textarea
                            name="flight_details"
                            id="flight_details"
                            rows="2"
                            placeholder="{{ __('tourism.respond.flight_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        >{{ old('flight_details') }}</textarea>
                    </div>

                    <div>
                        <label for="inclusions" class="block text-sm font-medium text-ink">{{ __('tourism.respond.inclusions_label') }}</label>
                        <textarea
                            name="inclusions"
                            id="inclusions"
                            rows="2"
                            placeholder="{{ __('tourism.respond.inclusions_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        >{{ old('inclusions') }}</textarea>
                    </div>

                    <div>
                        <label for="reply_text" class="block text-sm font-medium text-ink">{{ __('tourism.respond.notes_label') }}</label>
                        <textarea
                            name="reply_text"
                            id="reply_text"
                            rows="2"
                            placeholder="{{ __('tourism.respond.notes_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                        >{{ old('reply_text') }}</textarea>
                    </div>

                    <div>
                        <label for="attachment" class="block text-sm font-medium text-ink">{{ __('tourism.respond.attachment_label') }}</label>
                        <input type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="mt-1.5 block w-full text-sm text-ink">
                        <p class="mt-1 text-xs text-subtle">{{ __('tourism.respond.attachment_hint') }}</p>
                        @error('attachment')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark sm:w-auto">
                        {{ __('tourism.respond.submit_button') }}
                    </button>
                </form>
            @endif
        @endif
    </section>
@endsection
