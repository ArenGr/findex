<?php

namespace App\Parsers;

use App\Enums\RateType;

class VtbRateParser implements RateParser
{
    private const ITEM_PATTERN = '/<div class="wrapper-item">\s*<div class="wrapper-currency">.*?(?:\d+)\s+([A-Z]{3,4})\s*<\/strong>'
        .'.*?<div class="wrapper-purchase">.*?<\/svg>\s*([\d.]+)\s*<\/strong>'
        .'.*?<div class="wrapper-sale">.*?<\/svg>\s*([\d.]+)\s*<\/strong>/s';

    public function parse(string $html): array
    {
        $parts = preg_split('/<div class="tab-pane fade" id="options"/', $html, 2);

        if (count($parts) !== 2) {
            return [];
        }

        [$cashHtml, $nonCashHtml] = $parts;

        return [
            ...$this->buildRates($this->boundBlock($cashHtml), RateType::CASH),
            ...$this->buildRates($this->boundBlock($nonCashHtml), RateType::NON_CASH),
        ];
    }

    private function boundBlock(string $html): string
    {
        $start = strpos($html, 'wrapper-currency-data');

        if ($start === false) {
            return '';
        }

        $end = strpos($html, 'wrapper-currency-data', $start + 1);

        return $end !== false ? substr($html, $start, $end - $start) : substr($html, $start);
    }

    private function buildRates(string $html, RateType $rateType): array
    {
        preg_match_all(self::ITEM_PATTERN, $html, $matches, PREG_SET_ORDER);

        return array_map(fn ($m) => [
            'code' => $m[1],
            'rate_type' => $rateType->value,
            'buy' => (float) $m[2],
            'sell' => (float) $m[3],
        ], $matches);
    }
}
