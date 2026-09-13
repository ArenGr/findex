<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Currency;
use App\Services\MarketRateService;
use App\Services\RateHistoryService;
use Illuminate\View\View;

// One page per currency: "USD to AMD exchange rate today".
class CurrencyLandingController extends Controller
{
    public function show(
        string $locale,
        string $currency,
        MarketRateService $market,
        RateHistoryService $history,
    ): View {
        $selected = Currency::where('code', strtoupper($currency))->where('is_active', true)->firstOrFail();

        $type = RateType::CASH;
        $rates = $market->currentRates($selected, $type);

        abort_if($rates->isEmpty(), 404);

        $best = $market->best($selected, $type);
        $average = $market->average($selected, $type);

        $days = min(7, max($history->availableDays(), 1));
        $series = $history->marketSeries($selected->id, $type, $days);

        return view('rates.currency', [
            'currency' => $selected,
            'bestBuy' => $best['highest_buy'],
            'bestSell' => $best['lowest_sell'],
            'average' => $average,
            'topRates' => $rates->sortBy(fn ($rate) => (float) $rate->sell_rate)->take(5)->values(),
            'series' => $series,
            'days' => $days,
            'updatedAt' => $rates->max('scraped_at'),
        ]);
    }
}
