<?php

namespace App\Parsers;

use App\Enums\CurrencyCode;
use App\Enums\RateType;
use Symfony\Component\DomCrawler\Crawler;

class AmeriaRateParser implements RateParser
{
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $table = $this->findRatesTable($crawler);

        if ($table === null) {
            return [];
        }

        $rates = [];

        $table->filter('tr')->each(function (Crawler $row) use (&$rates) {
            $cells = $row->filter('td');

            // Rate rows have 5 cells: currency, cash buy/sell, non-cash buy/sell.
            if ($cells->count() < 5) {
                return;
            }

            $code = strtoupper(trim($cells->eq(0)->text()));

            if (! in_array($code, CurrencyCode::codes(), true)) {
                return;
            }

            $this->addRate($rates, $code, RateType::CASH->value, $cells->eq(1)->text(), $cells->eq(2)->text());
            $this->addRate($rates, $code, RateType::NON_CASH->value, $cells->eq(3)->text(), $cells->eq(4)->text());
        });

        return $rates;
    }

    // Find the rates table by its "cash" / "non-cash" header group.
    private function findRatesTable(Crawler $crawler): ?Crawler
    {
        $tables = $crawler->filter('table');

        foreach ($tables as $node) {
            $table = new Crawler($node);
            $headerText = strtolower($table->filter('tr')->first()->text());

            if (str_contains($headerText, 'cash') && str_contains($headerText, 'non-cash')) {
                return $table;
            }
        }

        return null;
    }

    // Append a rate row, skipping blank cells (e.g.
    private function addRate(array &$rates, string $code, string $rateType, string $buyText, string $sellText): void
    {
        $buy = (float) trim(str_replace("\u{00A0}", '', $buyText));
        $sell = (float) trim(str_replace("\u{00A0}", '', $sellText));

        if ($buy <= 0 || $sell <= 0) {
            return;
        }

        $rates[] = [
            'code' => $code,
            'rate_type' => $rateType,
            'buy' => $buy,
            'sell' => $sell,
        ];
    }
}
