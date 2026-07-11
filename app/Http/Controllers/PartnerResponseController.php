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
            ->with(['quoteRequest', 'organization'])
            ->first();

        return view('tourism.respond', ['response' => $response]);
    }

    public function store(Request $request, string $locale, string $token): RedirectResponse
    {
        $response = QuoteResponse::query()->where('response_token', $token)->with('quoteRequest')->firstOrFail();

        if ($response->status !== QuoteResponse::STATUS_PENDING || !$response->quoteRequest->is_open) {
            return redirect()->route('tourism.respond', ['locale' => $locale, 'token' => $token]);
        }

        $validated = $request->validate([
            'price_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'price_currency' => ['required', Rule::in(QuoteResponse::CURRENCIES)],
            'offered_hotel_name' => ['nullable', 'string', 'max:255'],
            'flight_details' => ['nullable', 'string', 'max:2000'],
            'inclusions' => ['nullable', 'string', 'max:2000'],
            'reply_text' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $response->update([
            'price_amount' => $validated['price_amount'],
            'price_currency' => $validated['price_currency'],
            'offered_hotel_name' => $validated['offered_hotel_name'] ?? null,
            'flight_details' => $validated['flight_details'] ?? null,
            'inclusions' => $validated['inclusions'] ?? null,
            'reply_text' => $validated['reply_text'] ?? null,
            'attachment_path' => $request->hasFile('attachment')
                ? $request->file('attachment')->store('quote-attachments', 'public')
                : null,
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

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
