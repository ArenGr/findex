<?php

namespace App\Parsers;

use App\Enums\RateType;

class FastBankRateParser implements RateParser
{
    private const PAY_TYPES = [
        // Not the ordering you would guess - 0 is the non-cash rate, not the cash one.
        0 => RateType::NON_CASH,
        1 => RateType::CASH,
        5 => RateType::CARD,
    ];

    public function parse(string $html): array
    {
        $data = json_decode($html, true);
        $rows = $data['Rates'] ?? null;

        if (! is_array($rows)) {
            return [];
        }

        $rates = [];

        foreach ($rows as $row) {
            $rate = is_array($row) ? $this->buildRate($row) : null;

            if ($rate !== null) {
                $rates[] = $rate;
            }
        }

        return $rates;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{code: string, rate_type: string, buy: float, sell: float}|null
     */
    private function buildRate(array $row): ?array
    {
        $code = $row['Id'] ?? null;

        if (! is_string($code) || trim($code) === '') {
            return null;
        }

        $payType = $row['PayType'] ?? null;

        if (! is_int($payType) || ! isset(self::PAY_TYPES[$payType])) {
            return null;
        }

        $buy = $row['Buy'] ?? null;
        $sell = $row['Sale'] ?? null;

        if (! is_numeric($buy) || ! is_numeric($sell)
            || (float) $buy <= 0.0 || (float) $sell <= 0.0) {
            return null;
        }

        $unit = $row['Unit'] ?? 1;

        if (! is_numeric($unit) || (float) $unit <= 0.0) {
            return null;
        }

        return [
            'code' => strtoupper(trim($code)),
            'rate_type' => self::PAY_TYPES[$payType]->value,
            'buy' => (float) $buy / (float) $unit,
            'sell' => (float) $sell / (float) $unit,
        ];
    }
}
