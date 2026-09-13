<?php

namespace App\Parsers;

use App\Enums\RateType;

class ConverseRateParser implements RateParser
{
    private const CATEGORIES = [
        'Cash' => RateType::CASH,
        'Non Cash' => RateType::NON_CASH,
        'Card' => RateType::CARD,
    ];

    // `iso2` is the currency the row is *quoted in*, and it is not always AMD.
    private const QUOTE_CURRENCY = 'AMD';

    public function parse(string $html): array
    {
        $data = json_decode($html, true);

        if (! is_array($data)) {
            return [];
        }

        $parsed = [];

        foreach (self::CATEGORIES as $group => $rateType) {
            $rows = $data[$group] ?? null;

            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $rate = is_array($row) ? $this->buildRate($row, $rateType) : null;

                if ($rate !== null) {
                    $parsed[] = $rate;
                }
            }
        }

        return $parsed;
    }

    /**
     * The API also publishes a "Metal" group (gold, XAU).
     *
     * @param  array<string, mixed>  $row
     * @return array{code: string, rate_type: string, buy: float, sell: float}|null
     */
    private function buildRate(array $row, RateType $rateType): ?array
    {
        $code = data_get($row, 'currency.iso');

        // Some rows carry `"currency": null` - a currency the bank has since retired, still quoted.
        if (! is_string($code) || trim($code) === '') {
            return null;
        }

        $quote = $row['iso2'] ?? null;

        if (! is_string($quote) || strtoupper(trim($quote)) !== self::QUOTE_CURRENCY) {
            return null;
        }

        $buy = $row['buy'] ?? null;
        $sell = $row['sell'] ?? null;

        if (! is_numeric($buy) || ! is_numeric($sell)) {
            return null;
        }

        if ((float) $buy <= 0.0 || (float) $sell <= 0.0) {
            return null;
        }

        return [
            'code' => strtoupper(trim($code)),
            'rate_type' => $rateType->value,
            'buy' => (float) $buy,
            'sell' => (float) $sell,
        ];
    }
}
