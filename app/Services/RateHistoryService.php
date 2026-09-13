<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\CurrencyRateHistory;
use App\Services\Cache\RateCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

// What the market did, day by day.
class RateHistoryService
{
    /** Ranges worth offering, longest first. Only those the data covers survive. */
    public const RANGES = [7, 30, 90, 365];

    // How many days of history we actually hold.
    public function availableDays(): int
    {
        $earliest = Cache::tags([RateCache::TAG])->remember(
            'rates.history.earliest',
            now()->addHour(),
            fn () => CurrencyRateHistory::min('scraped_at'),
        );

        return $earliest === null ? 0 : (int) Carbon::parse($earliest)->startOfDay()->diffInDays(now()) + 1;
    }

    /**
     * @return array<int, int>
     */
    public function offerableRanges(): array
    {
        $available = $this->availableDays();

        $ranges = array_values(array_filter(
            self::RANGES,
            fn (int $days) => $days <= $available,
        ));

        // Always offer something.
        return $ranges === [] ? [max(1, $available)] : $ranges;
    }

    /**
     * @return array<int, array{date: string, best_buy: float|null, best_sell: float|null, average_buy: float|null, average_sell: float|null}>
     */
    public function marketSeries(int $currencyId, RateType $type, int $days): array
    {
        return Cache::tags([RateCache::TAG])->remember(
            "rates.history.market.{$currencyId}.{$type->value}.{$days}",
            now()->addHour(),
            fn () => $this->computeMarketSeries($currencyId, $type, $days),
        );
    }

    private function computeMarketSeries(int $currencyId, RateType $type, int $days): array
    {
        $from = now()->startOfDay()->subDays($days - 1);

        $snapshots = CurrencyRateHistory::query()
            ->join('currency_rates', 'currency_rates.id', '=', 'currency_rate_history.currency_rate_id')
            ->join('organizations', 'organizations.id', '=', 'currency_rates.organization_id')
            ->where('currency_rates.currency_id', $currencyId)
            ->where('currency_rates.rate_type', $type->value)
            ->where('organizations.is_active', true)
            ->orderBy('currency_rate_history.scraped_at')
            ->get([
                'currency_rate_history.currency_rate_id as rate_id',
                'currency_rate_history.buy_rate',
                'currency_rate_history.sell_rate',
                'currency_rate_history.scraped_at',
            ]);

        if ($snapshots->isEmpty()) {
            return [];
        }

        $series = [];
        // The board as it stood at the end of each day, carried forward.
        $board = [];
        $cursor = 0;
        $ordered = $snapshots->values();

        for ($day = $from->copy(); $day <= now()->startOfDay(); $day->addDay()) {
            $endOfDay = $day->copy()->endOfDay();

            while ($cursor < $ordered->count() && Carbon::parse($ordered[$cursor]->scraped_at) <= $endOfDay) {
                $row = $ordered[$cursor];
                $board[$row->rate_id] = [(float) $row->buy_rate, (float) $row->sell_rate];
                $cursor++;
            }

            if ($board === []) {
                continue;
            }

            $buys = array_column($board, 0);
            $sells = array_column($board, 1);

            $series[] = [
                'date' => $day->toDateString(),
                // Best for the visitor: the highest anyone buys at, the lowest anyone sells at.
                'best_buy' => max($buys),
                'best_sell' => min($sells),
                'average_buy' => round(array_sum($buys) / count($buys), 2),
                'average_sell' => round(array_sum($sells) / count($sells), 2),
            ];
        }

        return $series;
    }

    /**
     * One organization's own rate over time, for a single currency.
     *
     * @return array<int, array{date: string, buy_rate: float, sell_rate: float}>
     */
    public function organizationSeries(int $organizationId, int $currencyId, RateType $type, int $days): array
    {
        return Cache::tags([RateCache::TAG])->remember(
            "rates.history.org.{$organizationId}.{$currencyId}.{$type->value}.{$days}",
            now()->addHour(),
            function () use ($organizationId, $currencyId, $type, $days) {
                $snapshots = CurrencyRateHistory::query()
                    ->join('currency_rates', 'currency_rates.id', '=', 'currency_rate_history.currency_rate_id')
                    ->where('currency_rates.organization_id', $organizationId)
                    ->where('currency_rates.currency_id', $currencyId)
                    ->where('currency_rates.rate_type', $type->value)
                    ->orderBy('currency_rate_history.scraped_at')
                    ->get([
                        'currency_rate_history.buy_rate',
                        'currency_rate_history.sell_rate',
                        'currency_rate_history.scraped_at',
                    ]);

                if ($snapshots->isEmpty()) {
                    return [];
                }

                $series = [];
                $current = null;
                $cursor = 0;

                for ($day = now()->startOfDay()->subDays($days - 1); $day <= now()->startOfDay(); $day->addDay()) {
                    $endOfDay = $day->copy()->endOfDay();

                    while ($cursor < $snapshots->count() && Carbon::parse($snapshots[$cursor]->scraped_at) <= $endOfDay) {
                        $current = $snapshots[$cursor];
                        $cursor++;
                    }

                    if ($current === null) {
                        continue;
                    }

                    $series[] = [
                        'date' => $day->toDateString(),
                        'buy_rate' => (float) $current->buy_rate,
                        'sell_rate' => (float) $current->sell_rate,
                    ];
                }

                return $series;
            },
        );
    }

    // Where today's figure sits against the period it closes.
    public function changeAgainstAverage(array $series, string $key): ?float
    {
        $values = array_values(array_filter(array_column($series, $key), fn ($value) => $value !== null));

        if (count($values) < 2) {
            return null;
        }

        $latest = end($values);
        $average = array_sum($values) / count($values);

        return $average == 0.0 ? null : round((($latest - $average) / $average) * 100, 2);
    }
}
