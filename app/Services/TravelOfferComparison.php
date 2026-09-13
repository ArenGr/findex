<?php

namespace App\Services;

use App\Models\QuoteRequest;
use App\Models\QuoteSuggestion;
use Illuminate\Support\Collection;

class TravelOfferComparison
{
    private const MIN_OFFERS_TO_RANK = 2;

    public function __construct(private CurrencyConverter $currencyConverter) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function for(QuoteRequest $quoteRequest): Collection
    {
        $offers = $quoteRequest->responses
            ->where('has_replied', true)
            ->flatMap(fn ($response) => $response->suggestions->map(fn ($suggestion) => [
                'offer' => tap($suggestion, fn ($suggestion) => $suggestion->setRelation('response', $response)),
                'response' => $response,
                'organization' => $response->organization,
                'comparable_price' => $this->comparablePrice($suggestion),
            ]))
            ->sortByDesc(fn ($row) => $row['response']->responded_at?->timestamp ?? 0)
            ->values();

        $lowestId = $this->lowestPricedId($offers);

        return $offers->map(fn ($row) => $row + [
            'badges' => $this->badgesFor($row['offer'], $row['offer']->id === $lowestId),
        ]);
    }

    /**
     * The offer id holding the genuinely lowest price, or null when that can't be established.
     *
     * @param  Collection<int, array<string, mixed>>  $offers
     */
    private function lowestPricedId(Collection $offers): ?int
    {
        $comparable = $offers->whereNotNull('comparable_price')->sortBy('comparable_price')->values();

        if ($comparable->count() < self::MIN_OFFERS_TO_RANK) {
            return null;
        }

        if ($comparable[0]['comparable_price'] === $comparable[1]['comparable_price']) {
            return null;
        }

        return $comparable[0]['offer']->id;
    }

    // The offer's price expressed in AMD, or null if it honestly can't be.
    private function comparablePrice(QuoteSuggestion $offer): ?float
    {
        if ($offer->price_currency === 'AMD') {
            return (float) $offer->price_amount;
        }

        return $this->currencyConverter->convert((float) $offer->price_amount, $offer->price_currency, 'AMD');
    }

    /**
     * Facts about the offer, never opinions.
     *
     * @return array<int, string>
     */
    private function badgesFor(QuoteSuggestion $offer, bool $isLowest): array
    {
        return collect([
            $isLowest ? 'lowest_price' : null,
            $offer->hotel_stars === QuoteSuggestion::MAX_HOTEL_STARS ? 'five_star' : null,
            $offer->flight_type === QuoteSuggestion::FLIGHT_DIRECT ? 'direct_flight' : null,
            $offer->meal_plan === QuoteRequest::MEAL_ALL_INCLUSIVE ? 'all_inclusive' : null,
        ])->filter()->values()->all();
    }
}
