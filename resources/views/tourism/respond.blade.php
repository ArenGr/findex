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

                @if ($response->reply_text)
                    <div class="mt-8 rounded-2xl border border-placeholder p-6">
                        <p class="text-sm text-ink">{{ $response->reply_text }}</p>
                    </div>
                @endif

                @foreach ($response->suggestions as $suggestion)
                    <div class="mt-4 rounded-2xl border border-placeholder p-6">
                        <h2 class="font-heading text-base font-semibold text-ink">
                            {{ __('tourism.respond.your_offer_heading') }}
                            @if ($response->suggestions->count() > 1)
                                <span class="text-sm font-normal text-subtle">({{ $loop->iteration }}/{{ $response->suggestions->count() }})</span>
                            @endif
                        </h2>

                        <p class="mt-3 font-heading text-2xl font-bold text-primary">
                            {{ rtrim(rtrim((string) $suggestion->price_amount, '0'), '.') }} {{ $suggestion->price_currency }}
                        </p>

                        <dl class="mt-3 space-y-1 text-sm text-ink">
                            @if ($suggestion->offered_hotel_name)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.hotel_label') }}:</dt> <dd class="inline">{{ $suggestion->offered_hotel_name }}</dd></div>
                            @endif
                            @if ($suggestion->flight_details)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.flight_label') }}:</dt> <dd class="inline">{{ $suggestion->flight_details }}</dd></div>
                            @endif
                            @if ($suggestion->inclusions)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.inclusions_label') }}:</dt> <dd class="inline">{{ $suggestion->inclusions }}</dd></div>
                            @endif
                        </dl>

                        @if ($suggestion->attachment_path)
                            <a href="{{ Storage::url($suggestion->attachment_path) }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                                {{ __('tourism.results.attachment_label') }} &darr;
                            </a>
                        @endif

                        @if ($suggestion->promo_code)
                            <div class="mt-3 rounded-xl border border-dashed border-primary/40 bg-primary/5 px-4 py-3 text-sm">
                                <p class="font-semibold text-ink">{{ __('tourism.respond.promo_code_label') }}: {{ $suggestion->promo_code }}</p>
                                @if ($suggestion->promo_note)
                                    <p class="mt-1 text-xs text-ink">{{ $suggestion->promo_note }}</p>
                                @endif
                                @if ($suggestion->is_claimed)
                                    <p class="mt-1 text-xs text-primary">
                                        {{ __('tourism.respond.promo_claimed_by', [
                                            'name' => $suggestion->claimedBy->name,
                                            'email' => $suggestion->claimedBy->email,
                                            'time' => $suggestion->claimed_at->diffForHumans(),
                                        ]) }}
                                    </p>
                                @else
                                    <p class="mt-1 text-xs text-subtle">{{ __('tourism.respond.promo_not_claimed_yet') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
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

                    @if ($request->budget_min_amd || $request->budget_max_amd)
                        <p class="mt-4 text-sm text-ink">
                            <span class="text-subtle">{{ __('tourism.respond.budget_label') }}:</span>
                            @if ($request->budget_min_amd && $request->budget_max_amd)
                                {{ number_format($request->budget_min_amd) }}–{{ number_format($request->budget_max_amd) }} {{ __('tourism.request.amd') }}
                            @elseif ($request->budget_min_amd)
                                {{ __('tourism.respond.budget_at_least', ['amount' => number_format($request->budget_min_amd)]) }}
                            @else
                                {{ __('tourism.respond.budget_up_to', ['amount' => number_format($request->budget_max_amd)]) }}
                            @endif
                        </p>
                    @endif

                    @if ($request->notes)
                        <p class="mt-4 rounded-xl bg-placeholder/20 px-4 py-3 text-sm text-ink">{{ $request->notes }}</p>
                    @endif
                </div>

                {{-- Offer form - a partner can send more than one suggestion (see
                     QuoteResponse::MAX_SUGGESTIONS) rather than being limited to
                     a single price. --}}
                <form
                    method="POST"
                    action="{{ route('tourism.respond.store', ['locale' => app()->getLocale(), 'token' => $response->response_token]) }}"
                    enctype="multipart/form-data"
                    class="mt-8 space-y-5 rounded-2xl border border-placeholder p-6 shadow-sm"
                    novalidate
                    x-data="{
                        maxSuggestions: {{ \App\Models\QuoteResponse::MAX_SUGGESTIONS }},
                        emptySuggestion: { price_amount: '', price_currency: '', offered_hotel_name: '', flight_details: '', inclusions: '', promo_code: '', promo_note: '' },
                        suggestions: @js(old('suggestions', [['price_amount' => '', 'price_currency' => '', 'offered_hotel_name' => '', 'flight_details' => '', 'inclusions' => '', 'promo_code' => '', 'promo_note' => '']])),
                        templates: @js($templates->map->only(['id', 'name', 'price_amount', 'price_currency', 'offered_hotel_name', 'flight_details', 'inclusions'])),
                        addSuggestion(template = null) {
                            if (this.suggestions.length >= this.maxSuggestions) return;
                            this.suggestions.push(template ? {
                                price_amount: template.price_amount ?? '',
                                price_currency: template.price_currency ?? '',
                                offered_hotel_name: template.offered_hotel_name ?? '',
                                flight_details: template.flight_details ?? '',
                                inclusions: template.inclusions ?? '',
                                promo_code: '',
                                promo_note: '',
                            } : { ...this.emptySuggestion });
                        },
                        removeSuggestion(index) {
                            this.suggestions.splice(index, 1);
                        },
                        applyTemplate(id) {
                            const template = this.templates.find(t => t.id == id);
                            if (template) this.addSuggestion(template);
                            $event.target.value = '';
                        },
                    }"
                >
                    @csrf

                    @if ($templates->isNotEmpty())
                        <div>
                            <label for="quote_template" class="block text-sm font-medium text-ink">{{ __('tourism.respond.template_label') }}</label>
                            <select
                                id="quote_template"
                                @change="applyTemplate($event.target.value)"
                                class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                            >
                                <option value="">{{ __('tourism.respond.template_placeholder') }}</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-subtle">{{ __('tourism.respond.template_hint') }}</p>
                        </div>
                    @endif

                    <template x-for="(suggestion, index) in suggestions" :key="index">
                        <div class="space-y-4 rounded-xl border border-placeholder p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-ink" x-text="'{{ __('tourism.respond.suggestion_label') }}'.replace(':number', index + 1)"></p>
                                <button type="button" x-show="suggestions.length > 1" @click="removeSuggestion(index)" class="text-xs font-medium text-red-600 hover:underline">
                                    {{ __('tourism.respond.remove_suggestion') }}
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.price_label') }}</label>
                                    <input
                                        type="number" step="0.01" min="0" required
                                        :name="`suggestions[${index}][price_amount]`"
                                        x-model="suggestion.price_amount"
                                        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.currency_label') }}</label>
                                    <select
                                        required
                                        :name="`suggestions[${index}][price_currency]`"
                                        x-model="suggestion.price_currency"
                                        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                    >
                                        <option value="">—</option>
                                        @foreach (\App\Models\QuoteResponse::CURRENCIES as $currency)
                                            <option value="{{ $currency }}">{{ $currency }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.hotel_label') }}</label>
                                <input
                                    type="text"
                                    :name="`suggestions[${index}][offered_hotel_name]`"
                                    x-model="suggestion.offered_hotel_name"
                                    placeholder="{{ __('tourism.respond.hotel_placeholder') }}"
                                    class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.flight_label') }}</label>
                                <textarea
                                    rows="2"
                                    :name="`suggestions[${index}][flight_details]`"
                                    x-model="suggestion.flight_details"
                                    placeholder="{{ __('tourism.respond.flight_placeholder') }}"
                                    class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.inclusions_label') }}</label>
                                <textarea
                                    rows="2"
                                    :name="`suggestions[${index}][inclusions]`"
                                    x-model="suggestion.inclusions"
                                    placeholder="{{ __('tourism.respond.inclusions_placeholder') }}"
                                    class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.attachment_label') }}</label>
                                <input type="file" :name="`suggestions[${index}][attachment]`" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="mt-1.5 block w-full text-sm text-ink">
                                <p class="mt-1 text-xs text-subtle">{{ __('tourism.respond.attachment_hint') }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4 border-t border-placeholder pt-4">
                                <div>
                                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.promo_code_label') }}</label>
                                    <input
                                        type="text" maxlength="50"
                                        :name="`suggestions[${index}][promo_code]`"
                                        x-model="suggestion.promo_code"
                                        placeholder="{{ __('tourism.respond.promo_code_placeholder') }}"
                                        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.promo_note_label') }}</label>
                                    <input
                                        type="text" maxlength="255"
                                        :name="`suggestions[${index}][promo_note]`"
                                        x-model="suggestion.promo_note"
                                        placeholder="{{ __('tourism.respond.promo_note_placeholder') }}"
                                        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                    >
                                </div>
                                <p class="col-span-2 text-xs text-subtle">{{ __('tourism.respond.promo_hint') }}</p>
                            </div>
                        </div>
                    </template>

                    @error('suggestions')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <button
                        type="button"
                        @click="addSuggestion()"
                        x-show="suggestions.length < maxSuggestions"
                        class="w-full border border-dashed border-placeholder px-4 py-2 text-sm font-medium text-primary hover:border-primary"
                    >
                        {{ __('tourism.respond.add_suggestion') }}
                    </button>

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

                    <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark sm:w-auto">
                        {{ __('tourism.respond.submit_button') }}
                    </button>
                </form>
            @endif
        @endif
    </section>
@endsection
