<?php

namespace App\Parsers;

use App\Enums\RateType;

class ArmswissbankRateParser implements RateParser
{
    private const CATEGORIES = [
        RateType::NON_CASH->value => ['BID', 'OFFER'],
        RateType::CASH->value => ['BID_cash', 'OFFER_cash'],
    ];

    public function parse(string $html): array
    {
        $data = json_decode($html, true);
        $rows = $data['lmasbrate'] ?? null;

        if (! is_array($rows)) {
            return [];
        }

        $rates = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = $row['ISO'] ?? null;

            if (! is_string($code) || trim($code) === '') {
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
                    'code' => strtoupper(trim($code)),
                    'rate_type' => $rateType,
                    'buy' => (float) $buy,
                    'sell' => (float) $sell,
                ];
            }
        }

        return $rates;
    }
}
