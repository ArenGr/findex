<?php

namespace App\Parsers;

use App\Enums\RateType;

class AcbaRateParser implements RateParser
{
    // Map of RateType => [buy field, sell field] in ACBA's JSON payload.
    private const RATE_TYPE_FIELDS = [
        RateType::CASH->value => ['buycashrate', 'sellcashrate'],
        RateType::CARD->value => ['buycardsrate', 'sellcardsrate'],
        RateType::TRANSFER->value => ['buyratefortransfer', 'sellratefortransfer'],
        RateType::CROSS->value => ['buyrateforcross', 'sellrateforcross'],
    ];

    // ACBA's site is an Angular app that embeds the exchange rates as JSON in the page markup.
    public function parse(string $html): array
    {
        preg_match_all('/\{"currency":"[A-Z]{2,4}"[^{}]*\}/', $html, $matches);

        $rates = [];

        foreach ($matches[0] as $json) {
            $data = json_decode($json, true);

            if (! is_array($data) || empty($data['currency'])) {
                continue;
            }

            $code = strtoupper($data['currency']);

            foreach (self::RATE_TYPE_FIELDS as $rateType => [$buyKey, $sellKey]) {
                if (! isset($data[$buyKey], $data[$sellKey])) {
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
