<?php

namespace App\Services;

use App\Models\QuoteRequest;
use App\Models\QuoteSuggestion;
use Illuminate\Support\Collection;

/**
 * Turns the offers on a request into the shape the offers list, the
 * comparison view and the offer detail page all read from - so a price
 * shown as the lowest in one place cannot be shown as merely mid-range in
 * another.
 *
 * The one rule everything here follows: never present a comparison that
 * isn't real. An offer whose currency can't be converted is carried
 * through with a null comparable price and simply takes no part in
 * ranking, rather than being compared as though its number were AMD.
 */
class TravelOfferComparison
{
    /**
     * Below two comparable prices there is no comparison to report -
     * calling a lone price "the lowest" says nothing, since there is
     * nothing it was lower than.
     */
    private const MIN_OFFERS_TO_RANK = 2;

    public function __construct(private CurrencyConverter $currencyConverter) {}

    /**
     * Every offer on the request, newest agency reply first, each decorated
     * with its comparable price and its factual badges.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function for(QuoteRequest $quoteRequest): Collection
    {
        $offers = $quoteRequest->responses
            ->where('has_replied', true)
            ->flatMap(fn ($response) => $response->suggestions->map(fn ($suggestion) => [
                // The parent is already in memory, but it was loaded from
                // the response's side, so the child doesn't know about it -
                // and QuoteSuggestion::is_expired reads it. Handing it back
                // here is the difference between one query and one per
                // offer on a page that exists to show many.
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
     * The offer id holding the genuinely lowest price, or null when that
     * can't be established. Null covers three different situations, all of
     * which mean the same thing to a reader: too few comparable offers, no
     * usable exchange rates, or a tie - a "lowest price" badge on one of
     * two identical prices would be picking a winner arbitrarily.
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

    /**
     * The offer's price expressed in AMD, or null if it honestly can't be.
     *
     * AMD is the pivot the rate data already uses, so an offer already
     * priced in AMD needs no conversion at all and carries no conversion
     * risk. A missing rate returns null rather than the raw figure -
     * treating "610 USD" as "610 AMD" would not be an approximation, it
     * would be wrong by a factor of about four hundred.
     */
    private function comparablePrice(QuoteSuggestion $offer): ?float
    {
        if ($offer->price_currency === 'AMD') {
            return (float) $offer->price_amount;
        }

        return $this->currencyConverter->convert((float) $offer->price_amount, $offer->price_currency, 'AMD');
    }

    /**
     * Facts about the offer, never opinions. Every badge here is something
     * the agency itself stated in a structured field - there is no "best
     * value" or "recommended", because nothing in this system knows that.
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
