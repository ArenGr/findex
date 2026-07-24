<?php

namespace App\Services;

use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
    public function __construct(private readonly CurrencyConverter $currencyConverter) {}

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

        // Deduped/sorted before hashing so callers passing the same
        // destinations in a different order (typicalPrices() passes every
        // destination; priceBenchmark() passes one org's served subset)
        // still share a cache entry. TTL-only, no tags: staleness of up to
        // 45 min is fine for a "typical price" teaser/benchmark, and write
        // paths (any QuoteSuggestion/QuoteResponse) are too scattered to be
        // worth tag-invalidating. Cached as a plain array, not a Collection
        // of stdClass - config/cache.php's 'serializable_classes' => false
        // blocks unserializing any object, but Collection::where()/pluck()/
        // avg() all use data_get() under the hood, which reads array keys
        // and object properties identically, so returning collect() over
        // plain arrays needs no caller-side changes.
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
