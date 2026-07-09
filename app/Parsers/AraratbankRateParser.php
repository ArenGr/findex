<?php

namespace App\Parsers;

use App\Enums\RateType;

class AraratbankRateParser implements RateParser
{
    /**
     * AraratBank's homepage embeds two full exchange-rate tables as plain
     * server-rendered HTML (no JS rendering involved) - a "cash" wrapper and
     * a "not-cash" wrapper, each with a 4-column table (currency, buy, sell,
     * central bank rate). Rows beyond the first few are marked class="dn"
     * (display:none, revealed by a "See more" button) but the data is
     * present in the static markup either way.
     */
    private const ROW_PATTERN = '/<td class="exchange__table-cell fb fs20">([A-Z]{3,4})<\/td>\s*'
        . '<td class="exchange__table-cell fs20">([\d.]+)<\/td>\s*'
        . '<td class="exchange__table-cell fs20">([\d.]+)<\/td>\s*'
        . '<td class="exchange__table-cell fs20">([\d.]+)<\/td>/';

    public function parse(string $html): array
    {
        $parts = preg_split('/<div class="exchange__wrapper not-cash/', $html, 2);

        if (count($parts) !== 2) {
            return [];
        }

        [$cashHtml, $nonCashHtml] = $parts;

        $cashRows = $this->extractRows($cashHtml);
        $nonCashRows = $this->extractRows($nonCashHtml);

        $rates = [
            ...$this->buildRates($cashRows, RateType::CASH),
            ...$this->buildRates($nonCashRows, RateType::NON_CASH),
        ];

        // One central-bank reference rate per currency is enough - take it
        // from the cash table's rows rather than duplicating it for both
        // rate types.
        foreach ($cashRows as $row) {
            $rates[] = [
                'code' => $row[1],
                'rate_type' => RateType::CENTRAL_BANK->value,
                'buy' => (float) $row[4],
                'sell' => (float) $row[4],
            ];
        }

        return $rates;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private function extractRows(string $html): array
    {
        // Each half's table is immediately followed by a "See more" button
        // in an "exchange__additional-box" div - stop there so a gold-price
        // table further down the page (which reuses the same currency-code
        // cell markup, e.g. "USD/1g") never gets swept in.
        $tableEnd = strpos($html, 'exchange__additional-box');
        $tableHtml = $tableEnd !== false ? substr($html, 0, $tableEnd) : $html;

        preg_match_all(self::ROW_PATTERN, $tableHtml, $matches, PREG_SET_ORDER);

        return $matches;
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}> $rows
     */
    private function buildRates(array $rows, RateType $rateType): array
    {
        return array_map(fn ($row) => [
            'code' => $row[1],
            'rate_type' => $rateType->value,
            'buy' => (float) $row[2],
            'sell' => (float) $row[3],
        ], $rows);
    }
}
