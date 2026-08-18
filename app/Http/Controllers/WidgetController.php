<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Currency;
use App\Services\MarketRateService;
use App\Services\RateHistoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Embeddable widgets.
 */
class WidgetController extends Controller
{
    private const TYPES = ['rate', 'best', 'converter', 'chart'];

    public function show(
        Request $request,
        string $type,
        MarketRateService $market,
        RateHistoryService $history,
    ): Response {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $currency = Currency::where('code', strtoupper((string) $request->query('currency', 'USD')))
            ->where('is_active', true)
            ->first();

        abort_if($currency === null, 404);

        $best = $market->cachedBest($currency, RateType::CASH);

        $series = $type === 'chart'
            ? $history->marketSeries($currency->id, RateType::CASH, min(7, max($history->availableDays(), 1)))
            : [];

        $response = response()->view('widgets.show', [
            'type' => $type,
            'currency' => $currency,
            'best' => $best,
            'series' => $series,
            // Two looks, because a widget has to sit on someone else's page
            // without being told what that page looks like.
            'dark' => $request->query('theme') === 'dark',
        ]);

        // The whole point is that this is framed by somebody else. The default
        // SAMEORIGIN would make the widget render everywhere except where it
        // is meant to.
        $response->headers->remove('X-Frame-Options');

        // Cached at the edge rather than per visitor: there is nothing personal
        // in here, and a popular host page could otherwise hit us hard.
        $response->headers->set('Cache-Control', 'public, max-age=300');

        return $response;
    }
}
