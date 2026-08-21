<?php

namespace App\Parsers;

use App\Enums\RateType;
use Symfony\Component\DomCrawler\Crawler;

class ByblosRateParser implements RateParser
{
    /**
     * Byblos Bank Armenia renders its rates server-side, as plain
     * <table class="currency_table"> markup:
     *
     *   | Currency | Buy    | Sell   |
     *   | USD      | 362.00 | 366.50 |
     *
     * The page holds three such tables, and they are NOT all exchange
     * rates. Alongside the cash and non-cash tables sits the bank's "base
     * rate" table, which uses the same class and the same first column but
     * publishes interest percentages:
     *
     *   | Currency | Percent |
     *   | AMD      | 8.27%   |
     *   | USD      | 4.36%   |
     *
     * Read positionally, that table would store USD at 4.36 dram. So a
     * table qualifies only if its header actually offers a buy and a sell
     * column; the percentage table has neither and is skipped by shape
     * rather than by its position on the page.
     */
    private const REQUIRED_HEADERS = ['buy', 'sell'];

    /**
     * Among the tables that do qualify, order is the only thing separating
     * them - the two carry identical markup and no distinguishing class,
     * differing only in which tab they sit behind ("Cash", "Non-cash").
     */
    private const CATEGORIES = [RateType::CASH, RateType::NON_CASH];

    public function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $tables = (new Crawler($html))->filter('table.currency_table');

        $rates = [];
        $matched = 0;

        foreach ($tables as $node) {
            $table = new Crawler($node);

            if (! $this->quotesBuyAndSell($table)) {
                continue;
            }

            $rateType = self::CATEGORIES[$matched] ?? null;
            $matched++;

            // More rate tables than the two we know how to label. Taking a
            // guess would file rates under the wrong type, so the extras
            // are left out and the known ones still publish.
            if ($rateType === null) {
                continue;
            }

            $rates = [...$rates, ...$this->buildRates($table, $rateType)];
        }

        return $rates;
    }

    private function quotesBuyAndSell(Crawler $table): bool
    {
        $headers = $table->filter('th')->each(
            fn (Crawler $cell) => strtolower(trim($cell->text('')))
        );

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, array{code: string, rate_type: string, buy: float, sell: float}> */
    private function buildRates(Crawler $table, RateType $rateType): array
    {
        $rates = [];

        $table->filter('tr')->each(function (Crawler $row) use (&$rates, $rateType) {
            $cells = $row->filter('td');

            if ($cells->count() < 3) {
                return;
            }

            $code = strtoupper(trim($cells->eq(0)->text('')));

            if (! preg_match('/^[A-Z]{3,4}$/', $code)) {
                return;
            }

            $buy = $this->toRate($cells->eq(1)->text(''));
            $sell = $this->toRate($cells->eq(2)->text(''));

            if ($buy === null || $sell === null) {
                return;
            }

            $rates[] = [
                'code' => $code,
                'rate_type' => $rateType->value,
                'buy' => $buy,
                'sell' => $sell,
            ];
        });

        return $rates;
    }

    /**
     * Figures are printed plainly ("362.00"), but thousands separators show
     * up on the larger ones, so they are stripped before casting.
     */
    private function toRate(string $value): ?float
    {
        $value = str_replace([',', ' ', "\u{a0}"], '', trim($value));

        if (! is_numeric($value) || (float) $value <= 0.0) {
            return null;
        }

        return (float) $value;
    }
}
