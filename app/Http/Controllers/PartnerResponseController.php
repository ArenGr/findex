<?php

namespace App\Http\Controllers;

use App\Mail\QuoteResponseReceived;
use App\Models\QuoteResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The secure, unauthenticated page a partner lands on after tapping "View &
 * Respond" in Telegram (see TelegramPartnerNotifier). No login, no
 * registration - the response_token embedded in the link is the only
 * credential, matching the MVP goal of partners only ever interacting when
 * they receive a notification.
 */
class PartnerResponseController extends Controller
{
    public function show(string $locale, string $token): View
    {
        // A bad/mistyped token gets a friendly on-brand message here rather
        // than Laravel's generic 404 page - the only other way to land on
        // this page is a fresh, valid link from Telegram, so a wrong token
        // is almost certainly a copy-paste slip, not an attack worth hiding
        // behind a plain 404.
        $response = QuoteResponse::query()
            ->where('response_token', $token)
            ->with(['quoteRequest', 'organization', 'suggestions.claimedBy'])
            ->first();

        // Only templates relevant to this specific request - generic
        // (destination_country null) or matching this trip's destination -
        // so the partner isn't picking through templates for countries
        // this lead has nothing to do with.
        $templates = $response
            ? $response->organization->quoteTemplates()
                ->where(fn ($query) => $query->whereNull('destination_country')
                    ->orWhere('destination_country', $response->quoteRequest->destination_country))
                ->get()
            : collect();

        return view('tourism.respond', ['response' => $response, 'templates' => $templates]);
    }

    public function store(Request $request, string $locale, string $token): RedirectResponse
    {
        $response = QuoteResponse::query()->where('response_token', $token)->with('quoteRequest')->firstOrFail();

        if ($response->status !== QuoteResponse::STATUS_PENDING || !$response->quoteRequest->is_open) {
            return redirect()->route('tourism.respond', ['locale' => $locale, 'token' => $token]);
        }

        $validated = $request->validate([
            'reply_text' => ['nullable', 'string', 'max:2000'],
            'suggestions' => ['required', 'array', 'min:1', 'max:'.QuoteResponse::MAX_SUGGESTIONS],
            'suggestions.*.price_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'suggestions.*.price_currency' => ['required', Rule::in(QuoteResponse::CURRENCIES)],
            'suggestions.*.offered_hotel_name' => ['nullable', 'string', 'max:255'],
            'suggestions.*.flight_details' => ['nullable', 'string', 'max:2000'],
            'suggestions.*.inclusions' => ['nullable', 'string', 'max:2000'],
            'suggestions.*.attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'suggestions.*.promo_code' => ['nullable', 'string', 'max:50'],
            'suggestions.*.promo_note' => ['nullable', 'string', 'max:255'],
        ]);

        $response->update([
            'reply_text' => $validated['reply_text'] ?? null,
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        foreach ($validated['suggestions'] as $index => $suggestion) {
            $response->suggestions()->create([
                'price_amount' => $suggestion['price_amount'],
                'price_currency' => $suggestion['price_currency'],
                'offered_hotel_name' => $suggestion['offered_hotel_name'] ?? null,
                'flight_details' => $suggestion['flight_details'] ?? null,
                'inclusions' => $suggestion['inclusions'] ?? null,
                'attachment_path' => $request->hasFile("suggestions.{$index}.attachment")
                    ? $request->file("suggestions.{$index}.attachment")->store('quote-attachments', 'public')
                    : null,
                'promo_code' => $suggestion['promo_code'] ?? null,
                'promo_note' => $suggestion['promo_note'] ?? null,
            ]);
        }

        $response->load('organization');
        $requesterEmail = $response->quoteRequest->requester_email;

        if ($requesterEmail) {
            Mail::to($requesterEmail)
                ->locale($response->quoteRequest->locale)
                ->send(new QuoteResponseReceived($response, $response->quoteRequest->signedResultsUrl()));
        }

        return redirect()->route('tourism.respond', ['locale' => $locale, 'token' => $token]);
    }
}
