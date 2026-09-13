<?php

namespace App\Services;

use App\Mail\QuoteResponseReceived;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class TravelOfferSubmission
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reply_text' => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'contact_telegram' => ['nullable', 'string', 'max:50'],
            'contact_instagram' => ['nullable', 'string', 'max:50'],
            'valid_until' => ['nullable', 'date', 'after:now'],

            'suggestions' => ['required', 'array', 'min:1', 'max:'.QuoteResponse::MAX_SUGGESTIONS],
            // Present when revising an existing option, absent when adding one.
            'suggestions.*.id' => ['nullable', 'integer'],
            'suggestions.*.price_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'suggestions.*.price_currency' => ['required', Rule::in(QuoteResponse::CURRENCIES)],
            'suggestions.*.offered_hotel_name' => ['nullable', 'string', 'max:255'],
            'suggestions.*.hotel_stars' => [
                'nullable', 'integer',
                'min:'.QuoteSuggestion::MIN_HOTEL_STARS,
                'max:'.QuoteSuggestion::MAX_HOTEL_STARS,
            ],
            'suggestions.*.flight_included' => ['nullable', 'boolean'],
            'suggestions.*.flight_type' => ['nullable', Rule::in(QuoteSuggestion::FLIGHT_TYPES)],
            'suggestions.*.flight_details' => ['nullable', 'string', 'max:2000'],
            'suggestions.*.meal_plan' => ['nullable', Rule::in(QuoteSuggestion::MEAL_PLANS)],
            'suggestions.*.transfer_included' => ['nullable', 'boolean'],
            'suggestions.*.insurance_included' => ['nullable', 'boolean'],
            'suggestions.*.inclusions' => ['nullable', 'string', 'max:2000'],
            'suggestions.*.attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'suggestions.*.promo_code' => ['nullable', 'string', 'max:50'],
            'suggestions.*.promo_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Writes the offer onto the response, replacing whatever was there.
     *
     * @param  array<string, mixed>  $validated
     */
    public function persist(QuoteResponse $response, array $validated, Request $request): void
    {
        $wasFirstReply = ! $response->has_replied;

        $response->update([
            'reply_text' => $validated['reply_text'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_whatsapp' => $validated['contact_whatsapp'] ?? null,
            'contact_telegram' => $validated['contact_telegram'] ?? null,
            'contact_instagram' => $validated['contact_instagram'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => $response->responded_at ?? now(),
        ]);

        $keptIds = [];

        foreach ($validated['suggestions'] as $index => $input) {
            $existing = isset($input['id'])
                ? $response->suggestions()->whereKey($input['id'])->first()
                : null;

            $attributes = [
                'price_amount' => $input['price_amount'],
                'price_currency' => $input['price_currency'],
                'offered_hotel_name' => $input['offered_hotel_name'] ?? null,
                'hotel_stars' => $input['hotel_stars'] ?? null,
                'flight_included' => $this->boolOrNull($input['flight_included'] ?? null),
                'flight_type' => $input['flight_type'] ?? null,
                'flight_details' => $input['flight_details'] ?? null,
                'meal_plan' => $input['meal_plan'] ?? null,
                'transfer_included' => $this->boolOrNull($input['transfer_included'] ?? null),
                'insurance_included' => $this->boolOrNull($input['insurance_included'] ?? null),
                'inclusions' => $input['inclusions'] ?? null,
                'promo_code' => $input['promo_code'] ?? null,
                'promo_note' => $input['promo_note'] ?? null,
            ];

            if ($request->hasFile("suggestions.{$index}.attachment")) {
                // The private disk, not 'public'.
                $attributes['attachment_path'] = $request->file("suggestions.{$index}.attachment")
                    ->store('quote-attachments');
            }

            $suggestion = $existing
                ? tap($existing)->update($attributes)
                : $response->suggestions()->create($attributes);

            $keptIds[] = $suggestion->id;
        }

        $response->suggestions()->whereKeyNot($keptIds)->delete();

        $response->load(['suggestions', 'organization']);

        $response->quoteRequest->markOffersReceived();

        // Only on the first reply.
        if ($wasFirstReply) {
            $this->notifyRequester($response->quoteRequest, $response);
        }
    }

    private function boolOrNull(mixed $value): ?bool
    {
        return $value === null || $value === '' ? null : (bool) $value;
    }

    private function notifyRequester(QuoteRequest $quoteRequest, QuoteResponse $response): void
    {
        $requesterEmail = $quoteRequest->requester_email;

        if (! $requesterEmail) {
            return;
        }

        Mail::to($requesterEmail)
            ->locale($quoteRequest->locale)
            ->send(new QuoteResponseReceived($response, $quoteRequest->signedOffersUrl()));
    }
}
