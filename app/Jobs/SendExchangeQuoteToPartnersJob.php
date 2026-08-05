<?php

namespace App\Jobs;

use App\Enums\RateType;
use App\Models\ExchangeQuoteRequest;
use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use App\Services\Notifications\ExchangeNotifierInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendExchangeQuoteToPartnersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public ExchangeQuoteRequest $exchangeQuoteRequest) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Matching partners is business logic and stays here regardless of
     * notification channel; an ExchangeQuoteResponse row (with its secure
     * respond token, and a snapshot of the partner's rate at this exact
     * moment) is created for every match up front, so the response exists
     * independently of whether this particular notification attempt
     * succeeds. Same shape as SendQuoteRequestToPartnersJob (travel).
     */
    public function handle(ExchangeNotifierInterface $notifier): void
    {
        $partners = Organization::exchangePartnersForCurrency(
            $this->exchangeQuoteRequest->currency_id,
            $this->exchangeQuoteRequest->preferred_city
        )
            ->with(['currencyRates' => fn ($query) => $query
                ->where('currency_id', $this->exchangeQuoteRequest->currency_id)
                ->where('rate_type', RateType::CASH)])
            ->get();

        foreach ($partners as $partner) {
            $postedRate = $partner->currencyRates->first()?->{$this->exchangeQuoteRequest->rate_field};

            if ($postedRate === null) {
                continue;
            }

            $response = ExchangeQuoteResponse::create([
                'exchange_quote_request_id' => $this->exchangeQuoteRequest->id,
                'organization_id' => $partner->id,
                'response_token' => Str::random(40),
                'status' => ExchangeQuoteResponse::STATUS_PENDING,
                'posted_rate' => $postedRate,
            ]);

            $response->setRelation('organization', $partner);
            $response->setRelation('exchangeQuoteRequest', $this->exchangeQuoteRequest);

            if (! $notifier->notify($response)) {
                Log::warning('Exchange quote partner notification failed', [
                    'exchange_quote_request_id' => $this->exchangeQuoteRequest->id,
                    'organization_id' => $partner->id,
                ]);
            }
        }
    }
}
