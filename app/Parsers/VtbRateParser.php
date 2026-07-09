<?php

namespace App\Parsers;

use App\Enums\RateType;

class VtbRateParser implements RateParser
{
    /**
     * VTB Armenia's currency page renders two Bootstrap tabs, both fully
     * present in the static HTML (only CSS/JS toggles which is visible, no
     * AJAX fetch involved) - #home (data-value="cash") and #options
     * (data-value="nonCash"), each containing a "wrapper-currency-data"
     * block of "wrapper-item" rows:
     *
     *   <div class="wrapper-item">
     *     <div class="wrapper-currency"><strong><img .../>1 USD</strong></div>
     *     <div class="wrapper-purchase"><strong><svg>...</svg>364.5</strong></div>
     *     <div class="wrapper-sale"><strong><svg>...</svg>369</strong></div>
     *   </div>
     *
     * Each purchase/sale value is preceded by one or two small trend-arrow
     * SVGs (with no fixed count), so the pattern below matches through to
     * whichever trailing </svg> immediately precedes the number.
     */
    private const ITEM_PATTERN = '/<div class="wrapper-item">\s*<div class="wrapper-currency">.*?(?:\d+)\s+([A-Z]{3,4})\s*<\/strong>'
        . '.*?<div class="wrapper-purchase">.*?<\/svg>\s*([\d.]+)\s*<\/strong>'
        . '.*?<div class="wrapper-sale">.*?<\/svg>\s*([\d.]+)\s*<\/strong>/s';

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

    /**
     * Bounds the search to just the first "wrapper-currency-data" block in
     * the given HTML - each tab pane also embeds a currency-converter widget
     * and (in the non-cash tab) a gold-rate table further down that reuse
     * the same "wrapper-item" markup.
     */
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
