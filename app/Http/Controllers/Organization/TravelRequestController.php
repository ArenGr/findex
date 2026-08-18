<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\QuoteResponse;
use App\Services\TravelOfferSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * An agency's inbox of travel requests it has been sent, and the form it
 * answers them on.
 *
 * Until this existed, the only way to answer a request was the token link
 * pushed to Telegram (see PartnerResponseController) - which meant an
 * agency that lost the message, or never connected Telegram at all, had no
 * way back to a request it had been sent. Same underlying rows, same
 * validation (see TravelOfferSubmission); the difference is only that here
 * the agency is authenticated, so the response token isn't the credential.
 */
class TravelRequestController extends Controller
{
    public function index(Request $request): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        // Two tabs for the only distinction that changes what the agency
        // does next: needs an answer, or already answered.
        $tab = $request->query('tab') === 'answered' ? 'answered' : 'open';

        $responses = QuoteResponse::query()
            ->where('organization_id', $organization->id)
            ->with(['quoteRequest', 'suggestions'])
            ->when(
                $tab === 'open',
                fn ($query) => $query
                    ->where('status', QuoteResponse::STATUS_PENDING)
                    ->whereHas('quoteRequest', fn ($query) => $query->open()),
                fn ($query) => $query->where('status', QuoteResponse::STATUS_RESPONDED),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('organizations.dashboard.travel-requests.index', [
            'responses' => $responses,
            'tab' => $tab,
            'openCount' => QuoteResponse::query()
                ->where('organization_id', $organization->id)
                ->where('status', QuoteResponse::STATUS_PENDING)
                ->whereHas('quoteRequest', fn ($query) => $query->open())
                ->count(),
        ]);
    }

    public function show(string $locale, string $response): View
    {
        $response = $this->ownedResponse($response);

        // The agency opening the request is what lets the traveler's status
        // page honestly say it's being reviewed (see QuoteResponse::markViewed).
        $response->markViewed();

        return view('organizations.dashboard.travel-requests.show', [
            'response' => $response,
            'quoteRequest' => $response->quoteRequest,
            'templates' => $response->organization->quoteTemplates()
                ->where(fn ($query) => $query->whereNull('destination_country')
                    ->orWhere('destination_country', $response->quoteRequest->destination_country))
                ->get(),
        ]);
    }

    public function store(Request $request, string $locale, string $response, TravelOfferSubmission $submission): RedirectResponse
    {
        $response = $this->ownedResponse($response);

        // 410, not 403: the agency is perfectly entitled to this request,
        // it just can't be answered any more - the traveler closed it or it
        // ran out (see QuoteResponse::is_editable).
        abort_unless($response->is_editable, 410);

        $submission->persist($response, $request->validate($submission->rules()), $request);

        return redirect()
            ->route('org.dashboard.travel-requests.show', $response)
            ->with('status', 'travel-offer-saved');
    }

    /**
     * Declining, so the traveler stops waiting on an answer that isn't
     * coming and the agency's inbox stops showing it as outstanding.
     */
    public function decline(string $locale, string $response): RedirectResponse
    {
        $response = $this->ownedResponse($response);

        abort_unless($response->is_editable, 410);

        $response->update(['status' => QuoteResponse::STATUS_DECLINED]);

        return redirect()
            ->route('org.dashboard.travel-requests.index')
            ->with('status', 'travel-request-declined');
    }

    /**
     * Scopes every lookup to the signed-in agency's own organization, so a
     * response id belonging to a competitor is a 404 here rather than
     * something to be authorized and refused - from this agency's side, it
     * simply isn't a request that exists.
     */
    private function ownedResponse(string $id): QuoteResponse
    {
        return QuoteResponse::query()
            ->where('organization_id', Auth::guard('organization')->user()->organization_id)
            ->with(['quoteRequest', 'suggestions', 'organization'])
            ->findOrFail($id);
    }
}
