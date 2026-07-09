<?php

namespace App\Parsers;

use App\Enums\RateType;

class ArtsakhbankRateParser implements RateParser
{
    /**
     * Artsakhbank's exchange-rates page renders two plain HTML tables inside
     * "tab_block cash_section" / "tab_block non_cash_section" divs:
     *
     *   <tr><td>USD</td><td>1</td><td>365.00</td><td>369.50</td><td>0.00</td><td>0.00</td></tr>
     *
     * (currency, unit, buy, sell, then two "rate difference" columns we
     * don't use). The div class values also appear earlier on the page as
     * `data-tab="cash_section"` tab-button attributes, so the search is
     * anchored on the full "tab_block ..._section" string to land on the
     * actual content div rather than the button.
     */
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
