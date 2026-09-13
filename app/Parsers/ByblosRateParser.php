<?php

namespace App\Parsers;

use App\Enums\RateType;
use Symfony\Component\DomCrawler\Crawler;

class ByblosRateParser implements RateParser
{
    private const REQUIRED_HEADERS = ['buy', 'sell'];

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

            // More rate tables than the two we know how to label.
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

    private function toRate(string $value): ?float
    {
        $value = str_replace([',', ' ', "\u{a0}"], '', trim($value));

        if (! is_numeric($value) || (float) $value <= 0.0) {
            return null;
        }

        return (float) $value;
    }
}
