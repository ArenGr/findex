@props(['template' => null, 'destinations'])

<x-form-input name="name" :label="__('org.quote_templates.name')" :value="old('name', $template->name ?? '')" required />

<div>
    <label for="destination_country" class="block text-sm font-medium text-ink">{{ __('org.quote_templates.destination') }}</label>
    <select
        name="destination_country"
        id="destination_country"
        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
    >
        <option value="">{{ __('org.quote_templates.any_destination') }}</option>
        @foreach ($destinations as $code)
            <option value="{{ $code }}" @selected(old('destination_country', $template->destination_country ?? null) === $code)>{{ __('destinations.' . $code) }}</option>
        @endforeach
    </select>
</div>

<div class="grid grid-cols-2 gap-4">
    <x-form-input type="number" step="0.01" min="0" name="price_amount" :label="__('tourism.respond.price_label')" :value="old('price_amount', $template->price_amount ?? '')" />

    <div>
        <label for="price_currency" class="block text-sm font-medium text-ink">{{ __('tourism.respond.currency_label') }}</label>
        <select
            name="price_currency"
            id="price_currency"
            class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
        >
            <option value="">—</option>
            @foreach (\App\Models\QuoteResponse::CURRENCIES as $currency)
                <option value="{{ $currency }}" @selected(old('price_currency', $template->price_currency ?? null) === $currency)>{{ $currency }}</option>
            @endforeach
        </select>
    </div>
</div>

<x-form-input name="offered_hotel_name" :label="__('tourism.respond.hotel_label')" :value="old('offered_hotel_name', $template->offered_hotel_name ?? '')" />

<div>
    <label for="flight_details" class="block text-sm font-medium text-ink">{{ __('tourism.respond.flight_label') }}</label>
    <textarea
        name="flight_details"
        id="flight_details"
        rows="2"
        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
    >{{ old('flight_details', $template->flight_details ?? '') }}</textarea>
</div>

<div>
    <label for="inclusions" class="block text-sm font-medium text-ink">{{ __('tourism.respond.inclusions_label') }}</label>
    <textarea
        name="inclusions"
        id="inclusions"
        rows="2"
        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
    >{{ old('inclusions', $template->inclusions ?? '') }}</textarea>
</div>

<div>
    <label for="reply_text" class="block text-sm font-medium text-ink">{{ __('tourism.respond.notes_label') }}</label>
    <textarea
        name="reply_text"
        id="reply_text"
        rows="2"
        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
    >{{ old('reply_text', $template->reply_text ?? '') }}</textarea>
</div>
