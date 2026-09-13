<?php

namespace App\Parsers;

use App\Enums\RateType;

class AraratbankRateParser implements RateParser
{
    private const ROW_PATTERN = '/<td class="exchange__table-cell fb fs20">([A-Z]{3,4})<\/td>\s*'
        .'<td class="exchange__table-cell fs20">([\d.]+)<\/td>\s*'
        .'<td class="exchange__table-cell fs20">([\d.]+)<\/td>\s*'
        .'<td class="exchange__table-cell fs20">([\d.]+)<\/td>/';

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
        $tableEnd = strpos($html, 'exchange__additional-box');
        $tableHtml = $tableEnd !== false ? substr($html, 0, $tableEnd) : $html;

        preg_match_all(self::ROW_PATTERN, $tableHtml, $matches, PREG_SET_ORDER);

        return $matches;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>  $rows
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
