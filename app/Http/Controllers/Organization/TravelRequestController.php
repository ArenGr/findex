<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\QuoteResponse;
use App\Services\TravelOfferSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// An agency's inbox of travel requests it has been sent, and the form it answers them on.
class TravelRequestController extends Controller
{
    public function index(Request $request): View
    {
        $organization = Auth::guard('organization')->user()->organization;

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

        abort_unless($response->is_editable, 410);

        $submission->persist($response, $request->validate($submission->rules()), $request);

        return redirect()
            ->route('org.dashboard.travel-requests.show', $response)
            ->with('status', 'travel-offer-saved');
    }

    public function decline(string $locale, string $response): RedirectResponse
    {
        $response = $this->ownedResponse($response);

        abort_unless($response->is_editable, 410);

        $response->update(['status' => QuoteResponse::STATUS_DECLINED]);

        return redirect()
            ->route('org.dashboard.travel-requests.index')
            ->with('status', 'travel-request-declined');
    }

    private function ownedResponse(string $id): QuoteResponse
    {
        return QuoteResponse::query()
            ->where('organization_id', Auth::guard('organization')->user()->organization_id)
            ->with(['quoteRequest', 'suggestions', 'organization'])
            ->findOrFail($id);
    }
}
