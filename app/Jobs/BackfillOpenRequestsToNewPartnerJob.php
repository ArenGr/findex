<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fired alongside NotifyDestinationAlertsJob when a tourism org starts
 * serving a destination (see TourismController::updateDestinations()).
 * NotifyDestinationAlertsJob only reaches people who explicitly asked to
 * be told - this instead reaches every customer who already has an *open*
 * request for that destination, so a newly joined agency isn't invisible
 * to a request that's still perfectly able to receive replies. Mirrors
 * SendQuoteRequestToPartnersJob's per-partner QuoteResponse creation, just
 * with the fan-out direction reversed (one new org, many existing
 * requests, instead of one new request, many existing orgs).
 */
class BackfillOpenRequestsToNewPartnerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizationId, public string $countryCode)
    {
    }

    public function handle(PartnerNotifierInterface $notifier): void
    {
        $organization = Organization::find($this->organizationId);

        if (!$organization) {
            return;
        }

        $openRequests = QuoteRequest::where('destination_country', $this->countryCode)
            ->open()
            ->whereDoesntHave('responses', fn ($query) => $query->where('organization_id', $organization->id))
            ->get();

        foreach ($openRequests as $quoteRequest) {
            // Re-checks the same lead-quality filter (min party size / min
            // budget) SendQuoteRequestToPartnersJob already applied when
            // this request first went out - a newly joined org shouldn't
            // get leads it would have been filtered out of had it already
            // been serving this destination.
            $qualifies = Organization::tourismPartnersForDestination(
                $this->countryCode,
                $quoteRequest->party_size,
                $quoteRequest->budget_for_filtering,
            )->whereKey($organization->id)->exists();

            if (!$qualifies) {
                continue;
            }

            $response = QuoteResponse::create([
                'quote_request_id' => $quoteRequest->id,
                'organization_id' => $organization->id,
                'response_token' => Str::random(40),
                'status' => QuoteResponse::STATUS_PENDING,
            ]);

            $response->setRelation('organization', $organization);
            $response->setRelation('quoteRequest', $quoteRequest);

            if (!$notifier->notify($response)) {
                Log::warning('Backfilled quote request partner notification failed', [
                    'quote_request_id' => $quoteRequest->id,
                    'organization_id' => $organization->id,
                ]);
            }
        }
    }
}
