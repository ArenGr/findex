<?php

namespace App\Parsers;

use App\Enums\RateType;

class MellatRateParser implements RateParser
{
    /**
     * Mellat Bank's site is an Angular app that renders no rate markup at
     * all - the served HTML is a 40 KB shell. It fills itself from the
     * bank's own API, which this parser reads:
     *
     *   https://api.mellatbank.am/api/v1/rate/list
     *
     *   {"status":200,"result":{"data":[
     *      {"currency":"USD","buy":362,"sell":367,
     *       "buyCash":362,"sellCash":367,"updated_at":"..."}, ...
     *   ]}}
     *
     * A short list - the bank quotes only the three major currencies.
     */
    private const CATEGORIES = [
        RateType::NON_CASH->value => ['buy', 'sell'],
        RateType::CASH->value => ['buyCash', 'sellCash'],
    ];

    /**
     * The dram is in the list, quoted against itself at 1/1. It is the
     * currency every other row is priced in, not something the bank trades,
     * and publishing it would put a meaningless "AMD 1.00 / 1.00" row on the
     * comparison page.
     */
    private const BASE_CURRENCY = 'AMD';

    public function parse(string $html): array
    {
        $data = json_decode($html, true);
        $rows = data_get($data, 'result.data');

        if (! is_array($rows)) {
            return [];
        }

        $rates = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = $row['currency'] ?? null;

            if (! is_string($code) || trim($code) === '') {
                continue;
            }

            $code = strtoupper(trim($code));

            if ($code === self::BASE_CURRENCY) {
                continue;
            }

            foreach (self::CATEGORIES as $rateType => [$buyKey, $sellKey]) {
                $buy = $row[$buyKey] ?? null;
                $sell = $row[$sellKey] ?? null;

                if (! is_numeric($buy) || ! is_numeric($sell)
                    || (float) $buy <= 0.0 || (float) $sell <= 0.0) {
                    continue;
                }

                $rates[] = [
                    'code' => $code,
                    'rate_type' => $rateType,
                    'buy' => (float) $buy,
                    'sell' => (float) $sell,
                ];
            }
        }

        return $rates;
    }
}
