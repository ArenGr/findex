<?php

namespace App\Services;

use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use Illuminate\Support\Collection;

/**
 * Shared raw material behind every historical-price feature in the tourism
 * vertical: the org-facing price benchmark (see
 * Organization\TourismController::priceBenchmark) and the public
 * typical-price teaser on the request form (see
 * QuoteRequestController::typicalPrices). Both need the same thing - every
 * already-responded suggestion for a set of destinations, priced in AMD -
 * and only differ in how they aggregate it afterward.
 */
class TourismPriceData
{
    public function __construct(private readonly CurrencyConverter $currencyConverter)
    {
    }

    /**
     * One row per responded suggestion, with its price converted to AMD
     * (dropped if no conversion rate is available - see
     * CurrencyConverter::convert()).
     */
    public function respondedSuggestionAmounts(array $countryCodes): Collection
    {
        if (empty($countryCodes)) {
            return collect();
        }

        return QuoteSuggestion::query()
            ->join('quote_responses', 'quote_responses.id', '=', 'quote_suggestions.quote_response_id')
            ->join('quote_requests', 'quote_requests.id', '=', 'quote_responses.quote_request_id')
            ->whereIn('quote_requests.destination_country', $countryCodes)
            ->where('quote_responses.status', QuoteResponse::STATUS_RESPONDED)
            ->select([
                'quote_responses.organization_id',
                'quote_requests.destination_country',
                'quote_suggestions.price_amount',
                'quote_suggestions.price_currency',
            ])
            ->get()
            ->map(fn ($row) => (object) [
                'organization_id' => $row->organization_id,
                'destination_country' => $row->destination_country,
                'amount_amd' => $this->currencyConverter->convert((float) $row->price_amount, $row->price_currency, 'AMD'),
            ])
            ->filter(fn ($row) => $row->amount_amd !== null);
    }
}
