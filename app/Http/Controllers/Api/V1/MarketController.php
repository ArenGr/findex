<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CurrencyRate;
use App\Services\MarketRateService;
use App\Services\RateHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MarketController extends ApiController
{
    /**
     * The best rate available in the country, on each side.
     *
     * Best for the caller's customer, not for the bank: the highest anyone buys
     * at, the lowest anyone sells at. Named so in the payload, because "best"
     * on its own is the ambiguity this whole product exists to remove.
     */
    public function best(Request $request, MarketRateService $market): JsonResponse
    {
        $currency = $this->currencyFromRequest($request);
        $type = $this->rateTypeFromRequest($request);

        // Shared with the widgets - see MarketRateService for why the two
        // must not compute this separately.
        ['highest_buy' => $bestBuy, 'lowest_sell' => $bestSell] = $market->best($currency, $type);

        $side = fn (?CurrencyRate $rate, string $field) => $rate === null ? null : [
            'rate' => (string) $rate->{$field},
            'organization' => ['slug' => $rate->organization->slug, 'name' => $rate->organization->name],
            'scraped_at' => $rate->scraped_at?->toIso8601String(),
        ];

        return response()->json([
            'data' => [
                'currency' => $currency->code,
                'rate_type' => $type->value,
                'highest_buy' => $side($bestBuy, 'buy_rate'),
                'lowest_sell' => $side($bestSell, 'sell_rate'),
            ],
        ]);
    }

    /** The mean across everyone currently quoting. */
    public function average(Request $request, MarketRateService $market): JsonResponse
    {
        $currency = $this->currencyFromRequest($request);
        $type = $this->rateTypeFromRequest($request);

        $average = $market->average($currency, $type);

        return response()->json([
            'data' => [
                'currency' => $currency->code,
                'rate_type' => $type->value,
                'organizations' => $average['organizations'],
                'average_buy' => $average['average_buy'] === null ? null : (string) $average['average_buy'],
                'average_sell' => $average['average_sell'] === null ? null : (string) $average['average_sell'],
            ],
        ]);
    }

    /**
     * Daily best and average, carried forward across days nobody repriced -
     * see RateHistoryService for why that matters.
     */
    public function history(Request $request, RateHistoryService $history): JsonResponse
    {
        $currency = $this->currencyFromRequest($request);
        $type = $this->rateTypeFromRequest($request);

        $available = $history->availableDays();
        $days = (int) $request->query('days', 7);

        if ($days < 1) {
            throw ValidationException::withMessages(['days' => 'days must be at least 1.']);
        }

        // Clamped rather than rejected: asking for a year and being handed
        // every day we hold is more useful than an error, as long as the
        // response says plainly how much that was.
        $days = min($days, max($available, 1));

        return response()->json([
            'data' => $history->marketSeries($currency->id, $type, $days),
            'meta' => [
                'currency' => $currency->code,
                'rate_type' => $type->value,
                'days' => $days,
                'days_available' => $available,
            ],
        ]);
    }
}
