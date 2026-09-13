<?php

namespace App\Parsers;

use App\Enums\RateType;

class UnibankRateParser implements RateParser
{
    // Map of the widget's own group names => our RateType.
    private const GROUP_RATE_TYPES = [
        'CASH' => RateType::CASH,
        'CASHLESS' => RateType::NON_CASH,
    ];

    public function parse(string $html): array
    {
        if (! preg_match('/currencies-converter\'\);\s*const\s+jsonData\s*=\s*\'(.*?)\'\s*;\s*BX\.CreoExchange\.init/s', $html, $match)) {
            return [];
        }

        $data = json_decode($match[1], true);

        if (! is_array($data)) {
            return [];
        }

        $rates = [];

        foreach (self::GROUP_RATE_TYPES as $group => $rateType) {
            foreach ($data[$group]['EXCHANGE_RATES'] ?? [] as $row) {
                if (empty($row['CURRENCY']) || ! isset($row['BUY'], $row['SELL'])) {
                    continue;
                }

                $rates[] = [
                    'code' => strtoupper($row['CURRENCY']),
                    'rate_type' => $rateType->value,
                    'buy' => (float) $row['BUY'],
                    'sell' => (float) $row['SELL'],
                ];
            }
        }

        return $rates;
    }
}
