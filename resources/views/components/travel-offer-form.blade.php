@props(['action', 'response', 'templates' => null])

@php
    use App\Models\QuoteResponse;
    use App\Models\QuoteSuggestion;

    $templates = $templates ?? collect();

    $blank = [
        'id' => null,
        'price_amount' => '',
        'price_currency' => '',
        'offered_hotel_name' => '',
        'hotel_stars' => '',
        'flight_included' => '',
        'flight_type' => '',
        'flight_details' => '',
        'meal_plan' => '',
        'transfer_included' => '',
        'insurance_included' => '',
        'inclusions' => '',
        'promo_code' => '',
        'promo_note' => '',
    ];

    // Three sources, in order of precedence: what the agency just submitted
    // and failed validation on, what it has already sent (so revising is
    // editing rather than retyping), and finally one blank option.
    $existing = $response->suggestions
        ->map(fn ($suggestion) => array_merge($blank, [
            'id' => $suggestion->id,
            'price_amount' => (string) $suggestion->price_amount,
            'price_currency' => $suggestion->price_currency,
            'offered_hotel_name' => $suggestion->offered_hotel_name ?? '',
            'hotel_stars' => (string) ($suggestion->hotel_stars ?? ''),
            'flight_included' => $suggestion->flight_included === null ? '' : (string) (int) $suggestion->flight_included,
            'flight_type' => $suggestion->flight_type ?? '',
            'flight_details' => $suggestion->flight_details ?? '',
            'meal_plan' => $suggestion->meal_plan ?? '',
            'transfer_included' => $suggestion->transfer_included === null ? '' : (string) (int) $suggestion->transfer_included,
            'insurance_included' => $suggestion->insurance_included === null ? '' : (string) (int) $suggestion->insurance_included,
            'inclusions' => $suggestion->inclusions ?? '',
            'promo_code' => $suggestion->promo_code ?? '',
            'promo_note' => $suggestion->promo_note ?? '',
        ]))
        ->values()
        ->all();

    $initial = old('suggestions', $existing !== [] ? $existing : [$blank]);

    $isRevision = $response->has_replied;

    // A three-way control, not a checkbox: "included", "not included" and
    // "didn't say" are three different answers, and a checkbox can only
    // carry two (see TravelOfferSubmission::boolOrNull).
    $tristate = [
        '' => __('tourism.offer.not_stated'),
        '1' => __('tourism.offer.included'),
        '0' => __('tourism.offer.not_included'),
    ];

    $inputClasses = 'mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none';
@endphp

<form
    method="POST"
    action="{{ $action }}"
    enctype="multipart/form-data"
    novalidate
    {{ $attributes->merge(['class' => 'space-y-5 rounded-2xl border border-placeholder bg-white p-6 shadow-sm']) }}
    x-data="{
        maxSuggestions: {{ QuoteResponse::MAX_SUGGESTIONS }},
        blank: @js($blank),
        suggestions: @js($initial),
        templates: @js($templates->map->only(['id', 'name', 'price_amount', 'price_currency', 'offered_hotel_name', 'flight_details', 'inclusions'])),
        addSuggestion(template = null) {
            if (this.suggestions.length >= this.maxSuggestions) return;
            this.suggestions.push(template
                ? { ...this.blank,
                    price_amount: template.price_amount ?? '',
                    price_currency: template.price_currency ?? '',
                    offered_hotel_name: template.offered_hotel_name ?? '',
                    flight_details: template.flight_details ?? '',
                    inclusions: template.inclusions ?? '' }
                : { ...this.blank });
        },
        removeSuggestion(index) {
            this.suggestions.splice(index, 1);
        },
        applyTemplate(id, select) {
            const template = this.templates.find((t) => t.id == id);
            if (template) this.addSuggestion(template);
            select.value = '';
        },
    }"
>
    @csrf

    @if ($templates->isNotEmpty())
        <div>
            <label for="quote_template" class="block text-sm font-medium text-ink">{{ __('tourism.respond.template_label') }}</label>
            <select id="quote_template" @change="applyTemplate($event.target.value, $event.target)" class="{{ $inputClasses }}">
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
                <p class="text-sm font-semibold text-ink" x-text="@js(__('tourism.respond.suggestion_label')).replace(':number', index + 1)"></p>
                <button type="button" x-show="suggestions.length > 1" @click="removeSuggestion(index)" class="text-xs font-medium text-red-600 hover:underline">
                    {{ __('tourism.respond.remove_suggestion') }}
                </button>
            </div>

            {{-- Carries the row's identity through a revision so an edit
                 updates this option rather than replacing it (and losing its
                 attachment) - see TravelOfferSubmission::persist(). --}}
            <input type="hidden" :name="`suggestions[${index}][id]`" :value="suggestion.id ?? ''">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.price_label') }}</label>
                    <input type="number" step="0.01" min="0" required :name="`suggestions[${index}][price_amount]`" x-model="suggestion.price_amount" class="{{ $inputClasses }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.currency_label') }}</label>
                    <select required :name="`suggestions[${index}][price_currency]`" x-model="suggestion.price_currency" class="{{ $inputClasses }}">
                        <option value="">—</option>
                        @foreach (QuoteResponse::CURRENCIES as $currency)
                            <option value="{{ $currency }}">{{ $currency }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.hotel_label') }}</label>
                    <input type="text" :name="`suggestions[${index}][offered_hotel_name]`" x-model="suggestion.offered_hotel_name" placeholder="{{ __('tourism.respond.hotel_placeholder') }}" class="{{ $inputClasses }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.hotel_stars_label') }}</label>
                    <select :name="`suggestions[${index}][hotel_stars]`" x-model="suggestion.hotel_stars" class="{{ $inputClasses }}">
                        <option value="">{{ __('tourism.offer.not_stated') }}</option>
                        @for ($stars = QuoteSuggestion::MIN_HOTEL_STARS; $stars <= QuoteSuggestion::MAX_HOTEL_STARS; $stars++)
                            <option value="{{ $stars }}">{{ $stars }}★</option>
                        @endfor
                    </select>
                </div>
            </div>

            {{-- The structured half of the offer. These are the fields the
                 traveler's comparison table lines up, so they are proper
                 controls rather than something to be written into the notes
                 box and hoped for. --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.flight_included_label') }}</label>
                    <select :name="`suggestions[${index}][flight_included]`" x-model="suggestion.flight_included" class="{{ $inputClasses }}">
                        @foreach ($tristate as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.flight_type_label') }}</label>
                    <select :name="`suggestions[${index}][flight_type]`" x-model="suggestion.flight_type" :disabled="suggestion.flight_included === '0'" class="{{ $inputClasses }} disabled:opacity-50">
                        <option value="">{{ __('tourism.offer.not_stated') }}</option>
                        @foreach (QuoteSuggestion::FLIGHT_TYPES as $type)
                            <option value="{{ $type }}">{{ __('tourism.flight_types.' . $type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.meal_plan_label') }}</label>
                    <select :name="`suggestions[${index}][meal_plan]`" x-model="suggestion.meal_plan" class="{{ $inputClasses }}">
                        <option value="">{{ __('tourism.offer.not_stated') }}</option>
                        @foreach (QuoteSuggestion::MEAL_PLANS as $plan)
                            <option value="{{ $plan }}">{{ __('tourism.meals.' . $plan) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.transfer_label') }}</label>
                    <select :name="`suggestions[${index}][transfer_included]`" x-model="suggestion.transfer_included" class="{{ $inputClasses }}">
                        @foreach ($tristate as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.insurance_label') }}</label>
                    <select :name="`suggestions[${index}][insurance_included]`" x-model="suggestion.insurance_included" class="{{ $inputClasses }}">
                        @foreach ($tristate as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.flight_label') }}</label>
                <textarea rows="2" :name="`suggestions[${index}][flight_details]`" x-model="suggestion.flight_details" placeholder="{{ __('tourism.respond.flight_placeholder') }}" class="{{ $inputClasses }}"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.inclusions_label') }}</label>
                <textarea rows="2" :name="`suggestions[${index}][inclusions]`" x-model="suggestion.inclusions" placeholder="{{ __('tourism.respond.inclusions_placeholder') }}" class="{{ $inputClasses }}"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.attachment_label') }}</label>
                <input type="file" :name="`suggestions[${index}][attachment]`" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="mt-1.5 block w-full text-sm text-ink">
                <p class="mt-1 text-xs text-subtle">
                    {{ $isRevision ? __('tourism.respond.attachment_keep_hint') : __('tourism.respond.attachment_hint') }}
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4 border-t border-placeholder pt-4">
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.promo_code_label') }}</label>
                    <input type="text" maxlength="50" :name="`suggestions[${index}][promo_code]`" x-model="suggestion.promo_code" placeholder="{{ __('tourism.respond.promo_code_placeholder') }}" class="{{ $inputClasses }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink">{{ __('tourism.respond.promo_note_label') }}</label>
                    <input type="text" maxlength="255" :name="`suggestions[${index}][promo_note]`" x-model="suggestion.promo_note" placeholder="{{ __('tourism.respond.promo_note_placeholder') }}" class="{{ $inputClasses }}">
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
        <label for="valid_until" class="block text-sm font-medium text-ink">{{ __('tourism.respond.valid_until_label') }}</label>
        <input
            type="datetime-local"
            id="valid_until"
            name="valid_until"
            value="{{ old('valid_until', $response->valid_until?->format('Y-m-d\TH:i')) }}"
            class="{{ $inputClasses }}"
        >
        <p class="mt-1 text-xs text-subtle">{{ __('tourism.respond.valid_until_hint') }}</p>
        @error('valid_until')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-4 rounded-xl border border-placeholder p-4">
        <div>
            <p class="text-sm font-semibold text-ink">{{ __('tourism.respond.contact_heading') }}</p>
            <p class="mt-1 text-xs text-subtle">{{ __('tourism.respond.contact_hint') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach (['contact_phone' => 'tel', 'contact_whatsapp' => 'tel', 'contact_telegram' => 'text', 'contact_instagram' => 'text'] as $field => $type)
                <div>
                    <label for="{{ $field }}" class="block text-sm font-medium text-ink">{{ __('tourism.respond.' . $field . '_label') }}</label>
                    <input
                        type="{{ $type }}"
                        id="{{ $field }}"
                        name="{{ $field }}"
                        maxlength="{{ Str::startsWith($field, 'contact_p') || $field === 'contact_whatsapp' ? 30 : 50 }}"
                        value="{{ old($field, $response->{$field}) }}"
                        placeholder="{{ __('tourism.respond.' . $field . '_placeholder') }}"
                        class="{{ $inputClasses }}"
                    >
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <label for="reply_text" class="block text-sm font-medium text-ink">{{ __('tourism.respond.notes_label') }}</label>
        <textarea name="reply_text" id="reply_text" rows="2" placeholder="{{ __('tourism.respond.notes_placeholder') }}" class="{{ $inputClasses }}">{{ old('reply_text', $response->reply_text) }}</textarea>
    </div>

    <button type="submit" class="w-full bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark sm:w-auto">
        {{ $isRevision ? __('tourism.respond.update_button') : __('tourism.respond.submit_button') }}
    </button>
</form>
