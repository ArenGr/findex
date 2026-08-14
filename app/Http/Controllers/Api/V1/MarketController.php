<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CurrencyRate;
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
    public function best(Request $request): JsonResponse
    {
        $currency = $this->currencyFromRequest($request);
        $type = $this->rateTypeFromRequest($request);

        $rates = CurrencyRate::query()
            ->with('organization')
            ->where('currency_id', $currency->id)
            ->where('rate_type', $type)
            ->whereHas('organization', fn ($query) => $query->active())
            ->get();

        $bestBuy = $rates->sortByDesc(fn (CurrencyRate $rate) => (float) $rate->buy_rate)->first();
        $bestSell = $rates->sortBy(fn (CurrencyRate $rate) => (float) $rate->sell_rate)->first();

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
    public function average(Request $request): JsonResponse
    {
        $currency = $this->currencyFromRequest($request);
        $type = $this->rateTypeFromRequest($request);

        $rates = CurrencyRate::query()
            ->where('currency_id', $currency->id)
            ->where('rate_type', $type)
            ->whereHas('organization', fn ($query) => $query->active())
            ->get();

        return response()->json([
            'data' => [
                'currency' => $currency->code,
                'rate_type' => $type->value,
                'organizations' => $rates->count(),
                'average_buy' => $rates->isEmpty() ? null : (string) round($rates->avg(fn ($rate) => (float) $rate->buy_rate), 4),
                'average_sell' => $rates->isEmpty() ? null : (string) round($rates->avg(fn ($rate) => (float) $rate->sell_rate), 4),
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
