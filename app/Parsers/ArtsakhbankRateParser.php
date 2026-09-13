<?php

namespace App\Parsers;

use App\Enums\RateType;

class ArtsakhbankRateParser implements RateParser
{
    private const ROW_PATTERN = '/<td>([A-Z]{3,4})<\/td>\s*<td>1<\/td>\s*<td>([\d.]+)\s*<\/td>\s*<td>([\d.]+)<\/td>/';

    public function parse(string $html): array
    {
        return [
            ...$this->buildRates($this->extractSection($html, 'tab_block cash_section'), RateType::CASH),
            ...$this->buildRates($this->extractSection($html, 'tab_block non_cash_section'), RateType::NON_CASH),
        ];
    }

    private function extractSection(string $html, string $marker): string
    {
        $start = strpos($html, $marker);

        if ($start === false) {
            return '';
        }

        $end = strpos($html, '</table>', $start);

        return $end !== false ? substr($html, $start, $end - $start) : '';
    }

    private function buildRates(string $html, RateType $rateType): array
    {
        preg_match_all(self::ROW_PATTERN, $html, $matches, PREG_SET_ORDER);

        return array_map(fn ($m) => [
            'code' => $m[1],
            'rate_type' => $rateType->value,
            'buy' => (float) $m[2],
            'sell' => (float) $m[3],
        ], $matches);
    }
}
