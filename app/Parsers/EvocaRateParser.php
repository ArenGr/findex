<?php

namespace App\Parsers;

use App\Enums\RateType;

class EvocaRateParser implements RateParser
{
    /**
     * Evocabank's homepage embeds its exchange-rate widget data as a plain
     * (non-JSON) JS object literal:
     *
     *   var currency = {
     *       cash : {
     *           buy : { AMD: 1, RUB: 4, USD: 365, ..., CAD: NaN, XAU: NaN, },
     *           sell : { AMD: 1, RUB: 4.65, USD: 370, ... }
     *       },
     *       non_cash : {
     *           buy : { ... },
     *           sell : { ... }
     *       }
     *   };
     *
     * Unquoted keys, trailing commas and `NaN` literals make this invalid
     * JSON, so it can't be json_decode'd - instead we lean on the fixed
     * structural order of the four buy/sell dicts (cash.buy, cash.sell,
     * non_cash.buy, non_cash.sell) and pull key/value pairs out of each with
     * a plain regex sweep.
     */
    public function parse(string $html): array
    {
        if (!preg_match('/var\s+currency\s*=\s*\{(.*?)\n\s*\};/s', $html, $match)) {
            return [];
        }

        $groups = [RateType::CASH, RateType::CASH, RateType::NON_CASH, RateType::NON_CASH];

        preg_match_all('/(?:buy|sell)\s*:\s*\{(.*?)\}/s', $match[1], $blockMatches);
        $blocks = array_map(fn ($block) => $this->extractPairs($block), $blockMatches[1]);

        if (count($blocks) < 4) {
            return [];
        }

        [$cashBuy, $cashSell, $nonCashBuy, $nonCashSell] = $blocks;

        return [
            ...$this->buildRates($cashBuy, $cashSell, RateType::CASH),
            ...$this->buildRates($nonCashBuy, $nonCashSell, RateType::NON_CASH),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function extractPairs(string $block): array
    {
        preg_match_all('/([A-Z]{3,4})\s*:\s*(-?[0-9.]+|NaN)\s*,?/', $block, $pairs, PREG_SET_ORDER);

        $rates = [];

        foreach ($pairs as $pair) {
            $rates[$pair[1]] = strtoupper($pair[2]) === 'NAN' ? null : (float) $pair[2];
        }

        return $rates;
    }

    /**
     * @param array<string, float|null> $buy
     * @param array<string, float|null> $sell
     */
    private function buildRates(array $buy, array $sell, RateType $rateType): array
    {
        $rates = [];

        foreach ($buy as $code => $buyValue) {
            $sellValue = $sell[$code] ?? null;

            if ($buyValue === null || $sellValue === null || $buyValue <= 0 || $sellValue <= 0) {
                continue;
            }

            $rates[] = [
                'code' => $code,
                'rate_type' => $rateType->value,
                'buy' => $buyValue,
                'sell' => $sellValue,
            ];
        }

        return $rates;
    }
}
