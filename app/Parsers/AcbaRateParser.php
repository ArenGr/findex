<?php

namespace App\Parsers;

use App\Enums\RateType;

class AcbaRateParser implements RateParser
{
    /**
     * Map of RateType => [buy field, sell field] in ACBA's JSON payload.
     */
    private const RATE_TYPE_FIELDS = [
        RateType::CASH->value => ['buycashrate', 'sellcashrate'],
        RateType::CARD->value => ['buycardsrate', 'sellcardsrate'],
        RateType::TRANSFER->value => ['buyratefortransfer', 'sellratefortransfer'],
        RateType::CROSS->value => ['buyrateforcross', 'sellrateforcross'],
    ];

    /**
     * ACBA's site is an Angular app that embeds the exchange rates as JSON in
     * the page markup. Each currency is an object shaped like:
     *
     *   {"currency":"USD","buy":"365","sell":"370","buycardsrate":"365",
     *    "sellcardsrate":"370","buycashrate":"365","sellcashrate":"370",
     *    "cbrate":"367.79", "buyrateforcross":"365", ...}
     *
     * Reading the JSON is far more stable than scraping the rendered table,
     * whose CSS classes are hashed and change between builds. Each currency
     * carries several distinct rate types (cash, card, transfer, cross) plus
     * a single central-bank reference rate, which we expand into separate rows.
     */
    public function parse(string $html): array
    {
        preg_match_all('/\{"currency":"[A-Z]{2,4}"[^{}]*\}/', $html, $matches);

        $rates = [];

        foreach ($matches[0] as $json) {
            $data = json_decode($json, true);

            if (!is_array($data) || empty($data['currency'])) {
                continue;
            }

            $code = strtoupper($data['currency']);

            foreach (self::RATE_TYPE_FIELDS as $rateType => [$buyKey, $sellKey]) {
                if (!isset($data[$buyKey], $data[$sellKey])) {
                    continue;
                }

                $rates[] = [
                    'code' => $code,
                    'rate_type' => $rateType,
                    'buy' => (float) $data[$buyKey],
                    'sell' => (float) $data[$sellKey],
                ];
            }

            // The central bank rate is a single reference value, not a buy/sell spread.
            if (isset($data['cbrate'])) {
                $rates[] = [
                    'code' => $code,
                    'rate_type' => RateType::CENTRAL_BANK->value,
                    'buy' => (float) $data['cbrate'],
                    'sell' => (float) $data['cbrate'],
                ];
            }
        }

        return $rates;
    }
}
