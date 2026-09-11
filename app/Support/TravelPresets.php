<?php

namespace App\Support;

use App\Models\QuoteRequest;
use Illuminate\Support\Carbon;

/**
 * The popular trips offered on the request page.
 *
 * A preset is a whole answer to step 1 and step 2 - where, when, how many,
 * flights, hotel class, board - so that choosing one leaves nothing to fill in
 * but the contact details a guest has to give anyway. It is not a package on
 * sale: the request that goes out is the same request as any other, and the
 * agencies still quote it themselves.
 *
 * Declared here rather than in the database because these are a handful of
 * editorial picks that change a few times a year, not records anyone
 * administers. They are keyed off QuoteRequest::DESTINATIONS, so a preset can
 * never name a country the form itself would refuse.
 */
class TravelPresets
{
    /**
     * How far ahead a preset's check-in is placed.
     *
     * Far enough out that agencies can actually price it - a request for next
     * weekend is one most of them decline - and near enough to read as a trip
     * the traveller could take. The dates are recomputed on every request, so
     * a preset never goes stale the way a hardcoded date would.
     */
    private const LEAD_DAYS = 30;

    /**
     * @return list<array{
     *     key: string, country: string, nights: int, adults: int,
     *     flight: string, hotel: string, meals: string, priorities: list<string>
     * }>
     */
    private const PRESETS = [
        [
            'key' => 'georgia_break',
            'country' => 'GE',
            'nights' => 4,
            'adults' => 2,
            'flight' => QuoteRequest::FLIGHT_NOT_NEEDED,
            'hotel' => '4',
            'meals' => QuoteRequest::MEAL_BREAKFAST,
            'priorities' => ['good_location', 'best_value'],
        ],
        [
            'key' => 'dubai_city',
            'country' => 'AE',
            'nights' => 5,
            'adults' => 2,
            'flight' => QuoteRequest::FLIGHT_INCLUDED,
            'hotel' => '4',
            'meals' => QuoteRequest::MEAL_BREAKFAST,
            'priorities' => ['direct_flight', 'good_location'],
        ],
        [
            'key' => 'egypt_all_in',
            'country' => 'EG',
            'nights' => 7,
            'adults' => 2,
            'flight' => QuoteRequest::FLIGHT_INCLUDED,
            'hotel' => '5',
            'meals' => QuoteRequest::MEAL_ALL_INCLUSIVE,
            'priorities' => ['all_inclusive', 'family_friendly'],
        ],
        [
            'key' => 'cyprus_sea',
            'country' => 'CY',
            'nights' => 7,
            'adults' => 2,
            'flight' => QuoteRequest::FLIGHT_INCLUDED,
            'hotel' => '4',
            'meals' => QuoteRequest::MEAL_HALF_BOARD,
            'priorities' => ['best_value', 'good_location'],
        ],
    ];

    /**
     * The presets, with their dates resolved and their typical price attached.
     *
     * @param  array<string, int|null>  $typicalPrices  keyed by country code,
     *                                                  as the request page already computes them
     * @return list<array<string, mixed>>
     */
    public static function all(array $typicalPrices = []): array
    {
        $checkIn = Carbon::today()->addDays(self::LEAD_DAYS);

        return collect(self::PRESETS)
            // A preset naming a country the form no longer offers would fill
            // the destination picker with a chip it cannot render.
            ->filter(fn (array $preset) => in_array($preset['country'], QuoteRequest::DESTINATIONS, true))
            ->map(fn (array $preset) => [
                ...$preset,
                'title' => __('tourism.presets.'.$preset['key'].'.title'),
                'summary' => __('tourism.presets.'.$preset['key'].'.summary'),
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkIn->copy()->addDays($preset['nights'])->toDateString(),
                // Null unless enough agencies have actually answered for this
                // destination - see QuoteRequestController::typicalPrices().
                'typical_price' => $typicalPrices[$preset['country']] ?? null,
            ])
            ->values()
            ->all();
    }
}
