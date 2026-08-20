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

class SendQuoteRequestToPartnersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public QuoteRequest $quoteRequest) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Matching partners is business logic and stays here regardless of
     * notification channel; a QuoteResponse row (with its secure respond
     * token) is created for every match up front, so the response - and the
     * link a partner would use to answer it - exists independently of
     * whether this particular notification attempt succeeds.
     */
    public function handle(PartnerNotifierInterface $notifier): void
    {
        // Capped, and matched the same way the submit-time count was - see
        // Organization::tourismPartnersForRequest(). A request open to
        // suggestions names no country and would otherwise reach every
        // agency on the platform.
        $partners = Organization::tourismPartnersForRequest($this->quoteRequest)->get();

        foreach ($partners as $partner) {
            $response = QuoteResponse::create([
                'quote_request_id' => $this->quoteRequest->id,
                'organization_id' => $partner->id,
                'response_token' => Str::random(40),
                'status' => QuoteResponse::STATUS_PENDING,
            ]);

            $response->setRelation('organization', $partner);
            $response->setRelation('quoteRequest', $this->quoteRequest);

            if (! $notifier->notify($response)) {
                Log::warning('Quote request partner notification failed', [
                    'quote_request_id' => $this->quoteRequest->id,
                    'organization_id' => $partner->id,
                ]);

                // Usually because the agency has no Telegram chat connected,
                // which is now a perfectly ordinary way to work (see the
                // dashboard inbox) rather than a misconfiguration - so it
                // gets told by email instead of not at all.
                AgencyRequestMailer::notify($response);
            }
        }
    }
}
