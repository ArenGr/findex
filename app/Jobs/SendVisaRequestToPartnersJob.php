<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\VisaRequest;
use App\Models\VisaResponse;
use App\Services\Notifications\VisaRequestMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SendVisaRequestToPartnersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public VisaRequest $visaRequest) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        $partners = Organization::visaPartners()->get();

        foreach ($partners as $partner) {
            // The row is what lets the agency answer, so it is created whether
            // or not the notification itself gets through.
            $response = VisaResponse::create([
                'visa_request_id' => $this->visaRequest->id,
                'organization_id' => $partner->id,
                'response_token' => Str::random(40),
                'status' => VisaResponse::STATUS_PENDING,
            ]);

            $response->setRelation('organization', $partner);
            $response->setRelation('visaRequest', $this->visaRequest);

            VisaRequestMailer::notify($response);
        }
    }
}
