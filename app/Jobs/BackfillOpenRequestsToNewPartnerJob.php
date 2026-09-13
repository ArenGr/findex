<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Services\Notifications\AgencyRequestMailer;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BackfillOpenRequestsToNewPartnerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizationId, public string $countryCode) {}

    public function handle(PartnerNotifierInterface $notifier): void
    {
        $organization = Organization::find($this->organizationId);

        if (! $organization) {
            return;
        }

        $openRequests = QuoteRequest::query()
            ->where(fn ($query) => $query
                ->whereJsonContains('destination_countries', $this->countryCode)
                ->orWhere('destination_country', $this->countryCode)
                ->orWhere('open_to_suggestions', true))
            ->open()
            ->whereDoesntHave('responses', fn ($query) => $query->where('organization_id', $organization->id))
            ->get();

        foreach ($openRequests as $quoteRequest) {
            $qualifies = Organization::tourismPartnersForDestination(
                $quoteRequest->destinations ?: null,
                $quoteRequest->party_size,
                $quoteRequest->budget_for_filtering,
            )->whereKey($organization->id)->exists();

            if (! $qualifies) {
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

            if (! $notifier->notify($response)) {
                Log::warning('Backfilled quote request partner notification failed', [
                    'quote_request_id' => $quoteRequest->id,
                    'organization_id' => $organization->id,
                ]);

                // Same email fallback as the initial fan-out - see SendQuoteRequestToPartnersJob.
                AgencyRequestMailer::notify($response);
            }
        }
    }
}
