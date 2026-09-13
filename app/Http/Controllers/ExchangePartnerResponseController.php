<?php

namespace App\Http\Controllers;

use App\Mail\ExchangeQuoteResponseReceived;
use App\Models\ExchangeQuoteResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExchangePartnerResponseController extends Controller
{
    public function show(string $locale, string $token): View
    {
        $response = ExchangeQuoteResponse::query()
            ->where('response_token', $token)
            ->with(['exchangeQuoteRequest.currency', 'organization'])
            ->first();

        return view('exchange.respond', ['response' => $response]);
    }

    // The office reporting what happened at the counter.
    public function outcome(Request $request, string $locale, string $token): RedirectResponse
    {
        $response = ExchangeQuoteResponse::query()->where('response_token', $token)->firstOrFail();

        $validated = $request->validate([
            'outcome' => ['required', Rule::in([
                ExchangeQuoteResponse::OUTCOME_COMPLETED,
                ExchangeQuoteResponse::OUTCOME_NO_SHOW,
            ])],
        ]);

        $response->recordOutcome($validated['outcome']);

        return redirect()->route('exchange.respond', ['locale' => $locale, 'token' => $token]);
    }

    public function store(Request $request, string $locale, string $token): RedirectResponse
    {
        $response = ExchangeQuoteResponse::query()->where('response_token', $token)->with('exchangeQuoteRequest')->firstOrFail();

        if ($response->status !== ExchangeQuoteResponse::STATUS_PENDING || ! $response->exchangeQuoteRequest->is_open) {
            return redirect()->route('exchange.respond', ['locale' => $locale, 'token' => $token]);
        }

        $validated = $request->validate([
            'offered_rate' => ['required', 'numeric', 'min:'.$response->posted_rate, 'max:99999999.9999'],
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
