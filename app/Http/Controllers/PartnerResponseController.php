<?php

namespace App\Http\Controllers;

use App\Models\QuoteResponse;
use App\Services\TravelOfferSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerResponseController extends Controller
{
    public function show(string $locale, string $token): View
    {
        $response = QuoteResponse::query()
            ->where('response_token', $token)
            ->with(['quoteRequest', 'organization', 'suggestions.claimedBy'])
            ->first();

        $response?->markViewed();

        $templates = $response
            ? $response->organization->quoteTemplates()
                ->where(fn ($query) => $query->whereNull('destination_country')
                    ->orWhere('destination_country', $response->quoteRequest->destination_country))
                ->get()
            : collect();

        return view('tourism.respond', ['response' => $response, 'templates' => $templates]);
    }

    // The agency downloading a file it attached to its own offer.
    public function attachment(string $locale, string $token, string $suggestion): StreamedResponse
    {
        $response = QuoteResponse::query()->where('response_token', $token)->firstOrFail();

        $offer = $response->suggestions()->whereKey($suggestion)->first();

        abort_if($offer === null || ! $offer->attachment_path, 404);

        return QuoteRequestController::downloadAttachment($offer);
    }

    public function store(Request $request, string $locale, string $token, TravelOfferSubmission $submission): RedirectResponse
    {
        $response = QuoteResponse::query()->where('response_token', $token)->with('quoteRequest')->firstOrFail();

        if ($response->status !== QuoteResponse::STATUS_PENDING || ! $response->quoteRequest->is_open) {
            return redirect()->route('tourism.respond', ['locale' => $locale, 'token' => $token]);
        }

        $submission->persist($response, $request->validate($submission->rules()), $request);

        return redirect()->route('tourism.respond', ['locale' => $locale, 'token' => $token]);
    }
}
