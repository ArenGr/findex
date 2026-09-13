<?php

namespace App\Parsers;

use App\Enums\RateType;

class InecoRateParser implements RateParser
{
    private const CATEGORIES = [
        'cash' => RateType::CASH,
        'cashless' => RateType::NON_CASH,
        'card' => RateType::CARD,
        'cb' => RateType::CENTRAL_BANK,
    ];

    private const UNMAPPED_CATEGORY = 'online';

    public function parse(string $html): array
    {
        $data = json_decode($html, true);
        $items = $data['items'] ?? null;

        if (! is_array($items)) {
            return [];
        }

        $rates = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = $item['code'] ?? null;

            if (! is_string($code) || trim($code) === '') {
                continue;
            }

            foreach (self::CATEGORIES as $key => $rateType) {
                $buy = data_get($item, "{$key}.buy");
                $sell = data_get($item, "{$key}.sell");

                if (! is_numeric($buy) || ! is_numeric($sell)
                    || (float) $buy <= 0.0 || (float) $sell <= 0.0) {
                    continue;
                }

                $rates[] = [
                    'code' => strtoupper(trim($code)),
                    'rate_type' => $rateType->value,
                    'buy' => (float) $buy,
                    'sell' => (float) $sell,
                ];
            }
        }

        return $rates;
    }
}
