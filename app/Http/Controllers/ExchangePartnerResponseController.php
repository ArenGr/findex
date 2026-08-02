<?php

namespace App\Http\Controllers;

use App\Mail\ExchangeQuoteResponseReceived;
use App\Models\ExchangeQuoteResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The secure, unauthenticated page an exchange office lands on after
 * tapping "View & Respond" in Telegram (see TelegramExchangeNotifier). No
 * login, no registration - the response_token embedded in the link is the
 * only credential. Same shape as PartnerResponseController (travel), but
 * the response itself is a single rate rather than several suggestions.
 */
class ExchangePartnerResponseController extends Controller
{
    public function show(string $locale, string $token): View
    {
        // A bad/mistyped token gets a friendly on-brand message rather than
        // a plain 404 - same reasoning as PartnerResponseController::show.
        $response = ExchangeQuoteResponse::query()
            ->where('response_token', $token)
            ->with(['exchangeQuoteRequest.currency', 'organization'])
            ->first();

        return view('exchange.respond', ['response' => $response]);
    }

    public function store(Request $request, string $locale, string $token): RedirectResponse
    {
        $response = ExchangeQuoteResponse::query()->where('response_token', $token)->with('exchangeQuoteRequest')->firstOrFail();

        if ($response->status !== ExchangeQuoteResponse::STATUS_PENDING || !$response->exchangeQuoteRequest->is_open) {
            return redirect()->route('exchange.respond', ['locale' => $locale, 'token' => $token]);
        }

        $validated = $request->validate([
            // Never below what was already posted - this form is strictly
            // "keep it or improve it", not a way to quote a worse rate than
            // what's already public on /rates.
            'offered_rate' => ['required', 'numeric', 'min:' . $response->posted_rate, 'max:99999999.9999'],
            'reply_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $response->update([
            'offered_rate' => $validated['offered_rate'],
            'reply_text' => $validated['reply_text'] ?? null,
            'status' => ExchangeQuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        $response->load('organization');
        $requesterEmail = $response->exchangeQuoteRequest->requester_email;

        if ($requesterEmail) {
            Mail::to($requesterEmail)
                ->locale($response->exchangeQuoteRequest->locale)
                ->send(new ExchangeQuoteResponseReceived($response, $response->exchangeQuoteRequest->signedResultsUrl()));
        }

        return redirect()->route('exchange.respond', ['locale' => $locale, 'token' => $token]);
    }
}
