<?php

namespace App\Parsers;

use App\Enums\RateType;

class ArdshinbankRateParser implements RateParser
{
    // Ardshinbank's site is a Nuxt app that renders no rate table server side.
    private const CATEGORIES = [
        'cash' => RateType::CASH,
        'no_cash' => RateType::NON_CASH,
    ];

    // The response also carries a "gold" branch, holding both per-gram bar prices and an XAU spread.
    public function parse(string $html): array
    {
        $data = json_decode($html, true);

        if (! is_array($data)) {
            return [];
        }

        $rates = [];
        $centralBank = [];

        foreach (self::CATEGORIES as $group => $rateType) {
            $rows = data_get($data, "data.currencies.{$group}");

            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $code = $this->code($row);

                if ($code === null) {
                    continue;
                }

                if ($this->isTradeable($row['buy'] ?? null, $row['sell'] ?? null)) {
                    $rates[] = [
                        'code' => $code,
                        'rate_type' => $rateType->value,
                        'buy' => (float) $row['buy'],
                        'sell' => (float) $row['sell'],
                    ];
                }

                $cb = $row['cb'] ?? null;

                if (! isset($centralBank[$code]) && is_numeric($cb) && (float) $cb > 0.0) {
                    $centralBank[$code] = (float) $cb;
                }
            }
        }

        foreach ($centralBank as $code => $rate) {
            $rates[] = [
                'code' => $code,
                'rate_type' => RateType::CENTRAL_BANK->value,
                'buy' => $rate,
                'sell' => $rate,
            ];
        }

        return $rates;
    }

    /** @param  array<string, mixed>  $row */
    private function code(array $row): ?string
    {
        $code = $row['type'] ?? null;

        if (! is_string($code) || trim($code) === '') {
            return null;
        }

        return strtoupper(trim($code));
    }

    private function isTradeable(mixed $buy, mixed $sell): bool
    {
        return is_numeric($buy) && is_numeric($sell)
            && (float) $buy > 0.0 && (float) $sell > 0.0;
    }
}
