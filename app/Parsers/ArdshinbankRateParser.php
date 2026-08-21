<?php

namespace App\Parsers;

use App\Enums\RateType;

class ArdshinbankRateParser implements RateParser
{
    /**
     * Ardshinbank's site is a Nuxt app that renders no rate table server
     * side. It fills itself from the bank's own JSON API, which this parser
     * reads instead:
     *
     *   https://ardshinbank.am/api/currency
     *
     *   {"data":{"currencies":{
     *       "cash":   [{"type":"USD","buy":"362","sell":"367","cb":"365.26"}, ...],
     *       "no_cash":[{"type":"USD","buy":"362.5","sell":"367.5","cb":"365.26"}, ...]
     *   },"gold":{ ... }}}
     *
     * Figures arrive as strings, and small ones come without a leading zero
     * (KZT is ".77"). Both cast cleanly.
     */
    private const CATEGORIES = [
        'cash' => RateType::CASH,
        'no_cash' => RateType::NON_CASH,
    ];

    /**
     * The response also carries a "gold" branch, holding both per-gram bar
     * prices and an XAU spread. Only data.currencies is read, so gold is
     * left out structurally rather than filtered by name.
     */
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

                // The central bank's reference rate is a property of the
                // currency, not of how it is traded, so the same figure is
                // repeated on the cash and non-cash rows. Keyed by code so
                // it is published once rather than twice.
                $cb = $row['cb'] ?? null;

                if (! isset($centralBank[$code]) && is_numeric($cb) && (float) $cb > 0.0) {
                    $centralBank[$code] = (float) $cb;
                }
            }
        }

        foreach ($centralBank as $code => $rate) {
            // A single reference value, not a spread - both sides carry it,
            // matching every other parser that publishes this rate type.
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

    /**
     * A zero on either side means the bank is not trading that currency
     * today, which would otherwise show as a 0 column on the comparison
     * page rather than as an absent rate.
     */
    private function isTradeable(mixed $buy, mixed $sell): bool
    {
        return is_numeric($buy) && is_numeric($sell)
            && (float) $buy > 0.0 && (float) $sell > 0.0;
    }
}
