<?php

namespace App\Http\Controllers;

use App\Models\QuoteResponse;
use App\Services\TravelOfferSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Opening the request is what "reviewing" means on the traveler's
        // status page - recorded here, at the only moment we actually learn
        // it (see QuoteResponse::markViewed, which only records the first).
        $response?->markViewed();

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

    /**
     * The agency downloading a file it attached to its own offer.
     *
     * The response_token is the credential here, exactly as it is for the
     * page the link sits on - the file itself lives on the private disk, so
     * this is the only way to it (see TravelOfferSubmission).
     */
    public function attachment(string $locale, string $token, string $suggestion): StreamedResponse
    {
        $response = QuoteResponse::query()->where('response_token', $token)->firstOrFail();

        // Scoped to this response, so one agency's token cannot fetch
        // another's attachment by guessing a suggestion id.
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

        // Shared with the dashboard inbox (see TravelOfferSubmission) so an
        // offer means the same thing whichever way the agency sent it -
        // including marking the request as having offers and emailing the
        // traveler on the first reply.
        $submission->persist($response, $request->validate($submission->rules()), $request);

        return redirect()->route('tourism.respond', ['locale' => $locale, 'token' => $token]);
    }
}
