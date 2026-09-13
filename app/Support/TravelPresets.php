<?php

namespace App\Support;

use App\Models\QuoteRequest;
use Illuminate\Support\Carbon;

// The popular trips offered on the request page.
class TravelPresets
{
    // How far ahead a preset's check-in is placed.
    private const LEAD_DAYS = 30;

    /**
     * @return list<array{
     *     key: string, city: string, country: string, nights: int, adults: int,
     *     flight: string, hotel: string, meals: string, priorities: list<string>
     * }>
     */
    private const PRESETS = [
        [
            'key' => 'georgia_break',
            'city' => 'tbilisi',
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
            'city' => 'dubai',
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
            'city' => 'hurghada',
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
            'city' => 'ayia_napa',
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
            ->filter(fn (array $preset) => in_array($preset['country'], QuoteRequest::DESTINATIONS, true))
            ->map(fn (array $preset) => [
                ...$preset,
                'title' => __('tourism.presets.'.$preset['key'].'.title'),
                'summary' => __('tourism.presets.'.$preset['key'].'.summary'),
                'city_label' => __('tourism.presets.cities.'.$preset['city']),
                'photo' => TravelHero::asset('preset-'.$preset['key']),
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkIn->copy()->addDays($preset['nights'])->toDateString(),
                'typical_price' => $typicalPrices[$preset['country']] ?? null,
            ])
            ->values()
            ->all();
    }
}
