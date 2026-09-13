<?php

namespace App\Services;

use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TourismPriceData
{
    public function __construct(private readonly CurrencyConverter $currencyConverter) {}

    public function respondedSuggestionAmounts(array $countryCodes): Collection
    {
        if (empty($countryCodes)) {
            return collect();
        }

        $sorted = collect($countryCodes)->unique()->sort()->values()->all();

        $rows = Cache::remember(
            'tourism.price_data.'.md5(implode(',', $sorted)),
            now()->addMinutes(45),
            fn () => QuoteSuggestion::query()
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
                ->map(fn ($row) => [
                    'organization_id' => $row->organization_id,
                    'destination_country' => $row->destination_country,
                    'amount_amd' => $this->currencyConverter->convert((float) $row->price_amount, $row->price_currency, 'AMD'),
                ])
                ->filter(fn ($row) => $row['amount_amd'] !== null)
                ->values()
                ->all()
        );

        return collect($rows);
    }
}
