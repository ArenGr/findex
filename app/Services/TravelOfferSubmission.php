<?php

namespace App\Services;

use App\Mail\QuoteResponseReceived;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Submitting or revising an agency's offer, shared by the two places it can
 * happen: the token link an agency follows from Telegram, and the inbox in
 * its Findex dashboard. Both must validate and store identically - an offer
 * sent one way and revised the other cannot be allowed to mean two
 * different things.
 */
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
            // An agency that puts a deadline on its price has to put it in
            // the future - a quote that expired before it was sent is not a
            // quote. Optional: not every agency works to a deadline, and
            // an offer without one simply never expires.
            'valid_until' => ['nullable', 'date', 'after:now'],

            'suggestions' => ['required', 'array', 'min:1', 'max:'.QuoteResponse::MAX_SUGGESTIONS],
            // Present when revising an existing option, absent when adding
            // one. Ownership is checked in persist() rather than here -
            // a rule can't see which response is being edited.
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
     * Options carrying an id are revised in place, ones without are added,
     * and any the agency left out are removed - which is what "I've changed
     * my offer" has to mean if an agency is ever to be able to withdraw one
     * of two options it sent.
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
            // Set once, on the first reply - a revision isn't a new answer,
            // and moving this would misreport how quickly the agency
            // actually got back (see Organization::isFastResponder()).
            'responded_at' => $response->responded_at ?? now(),
        ]);

        $keptIds = [];

        foreach ($validated['suggestions'] as $index => $input) {
            // Scoped to this response, so an id belonging to another
            // agency's offer resolves to null and is treated as a new
            // option rather than letting one agency overwrite another's.
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
                // The private disk, not 'public'. A quote attachment is one
                // traveller's pricing, and a file on the public disk is a
                // permanent unauthenticated URL - no expiry, no revocation,
                // readable by anyone it is ever forwarded to. Served instead
                // through a route that checks who is asking (see
                // QuoteRequestController::offerAttachment()).
                $attributes['attachment_path'] = $request->file("suggestions.{$index}.attachment")
                    ->store('quote-attachments');
            }
            // No new file on a revision leaves the existing attachment
            // alone - re-uploading the same PDF to change a price would be
            // a pointless thing to demand.

            $suggestion = $existing
                ? tap($existing)->update($attributes)
                : $response->suggestions()->create($attributes);

            $keptIds[] = $suggestion->id;
        }

        $response->suggestions()->whereKeyNot($keptIds)->delete();

        $response->load(['suggestions', 'organization']);

        $response->quoteRequest->markOffersReceived();

        // Only on the first reply. A traveler who has already been told
        // this agency answered doesn't need telling again every time a
        // typo is fixed.
        if ($wasFirstReply) {
            $this->notifyRequester($response->quoteRequest, $response);
        }
    }

    /**
     * An unchecked checkbox submits nothing at all, which is
     * indistinguishable from "the agency didn't say" - so a missing value
     * stays null rather than being read as a definite "not included".
     * The forms post an explicit 0/1 for exactly this reason.
     */
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
