<?php

namespace App\Parsers;

use App\Enums\CurrencyCode;
use App\Enums\RateType;

class InecoRateParser implements RateParser
{
    /**
     * Inecobank renders the rates as visible text in the page, e.g.
     * "USD 365.5 370 EUR 415 426.5 RUB 4 4.8". We strip the tags and read the
     * currency/buy/sell triples out of the resulting text.
     *
     * The board only shows a single (cash) rate per currency - Inecobank's
     * page doesn't distinguish card/transfer/cross rates the way ACBA's does.
     *
     * NOTE: adjust once a real Inecobank fixture is available - the live page
     * is behind Cloudflare, so this is based on the previously observed layout.
     */
    public function parse(string $html): array
    {
        $text = strip_tags($html);
        $codes = implode('|', CurrencyCode::codes());

        preg_match_all(
            '/\b(' . $codes . ')\b\s+([0-9,.]+)\s+([0-9,.]+)/i',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        $rates = [];

        foreach ($matches as $match) {
            $rates[] = [
                'code' => strtoupper($match[1]),
                'rate_type' => RateType::CASH->value,
                'buy'  => (float) str_replace(',', '.', $match[2]),
                'sell' => (float) str_replace(',', '.', $match[3]),
            ];
        }

        return $rates;
    }
}
