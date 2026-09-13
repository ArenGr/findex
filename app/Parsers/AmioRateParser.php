<?php

namespace App\Parsers;

use App\Enums\RateType;

class AmioRateParser implements RateParser
{
    private const CATEGORIES = [
        'cash' => RateType::CASH,
        'card' => RateType::NON_CASH,
    ];

    // Rates are published per branch.
    private const HEAD_OFFICE = '00';

    public function parse(string $html): array
    {
        $data = $this->extractNextData($html);

        $rates = data_get($data, 'props.pageProps.data.rates');

        if (! is_array($rates)) {
            return [];
        }

        $parsed = [];

        foreach (self::CATEGORIES as $key => $rateType) {
            foreach ($rates[$key] ?? [] as $row) {
                $rate = $this->buildRate($row, $rateType);

                if ($rate !== null) {
                    $parsed[] = $rate;
                }
            }
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractNextData(string $html): ?array
    {
        if (! preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
            return null;
        }

        return json_decode($matches[1], true);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{code: string, rate_type: string, buy: float, sell: float}|null
     */
    private function buildRate(mixed $row, RateType $rateType): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $code = $row['currency'] ?? null;
        $buy = $row['buyValue'] ?? null;
        $sell = $row['sellValue'] ?? null;

        if (! is_string($code) || ! is_numeric($buy) || ! is_numeric($sell)) {
            return null;
        }

        // A branch other than the head office is skipped rather than merged.
        if (isset($row['branchIndex']) && (string) $row['branchIndex'] !== self::HEAD_OFFICE) {
            return null;
        }

        if ((float) $buy <= 0 || (float) $sell <= 0) {
            return null;
        }

        return [
            'code' => strtoupper($code),
            'rate_type' => $rateType->value,
            'buy' => (float) $buy,
            'sell' => (float) $sell,
        ];
    }
}
