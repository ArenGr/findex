<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Currency;
use App\Services\Cache\RateCache;
use App\Services\RateHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RateHistoryController extends Controller
{
    public function index(Request $request, RateHistoryService $history): View
    {
        $currencies = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.currencies.active',
            now()->addMinutes(360),
            fn () => Currency::where('is_active', true)->orderBy('sort_order')->get()->toArray(),
        ))->map(fn (array $row) => (object) $row);

        $selectedCurrency = $currencies->firstWhere('code', $request->query('currency')) ?? $currencies->first();

        // Cash only for now.
        $type = RateType::CASH;

        $ranges = $history->offerableRanges();
        $days = in_array((int) $request->query('days'), $ranges, true)
            ? (int) $request->query('days')
            : $ranges[0];

        $series = $selectedCurrency === null
            ? []
            : $history->marketSeries($selectedCurrency->id, $type, $days);

        return view('rates.history', [
            'currencies' => $currencies,
            'selectedCurrency' => $selectedCurrency,
            'ranges' => $ranges,
            'days' => $days,
            'series' => $series,
            'availableDays' => $history->availableDays(),
            'pendingRanges' => array_values(array_diff(RateHistoryService::RANGES, $ranges)),
            'buyChange' => $history->changeAgainstAverage($series, 'best_buy'),
            'sellChange' => $history->changeAgainstAverage($series, 'best_sell'),
        ]);
    }
}
