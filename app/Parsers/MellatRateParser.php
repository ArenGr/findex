<?php

namespace App\Parsers;

use App\Enums\RateType;

class MellatRateParser implements RateParser
{
    private const CATEGORIES = [
        RateType::NON_CASH->value => ['buy', 'sell'],
        RateType::CASH->value => ['buyCash', 'sellCash'],
    ];

    // The dram is in the list, quoted against itself at 1/1.
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
