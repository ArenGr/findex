<?php

namespace App\Parsers;

use App\Enums\RateType;

class ArmeconombankRateParser implements RateParser
{
    // Map of RateType => [buy field, sell field] in AEB's JSON payload.
    private const RATE_TYPE_FIELDS = [
        RateType::CASH->value => ['buy', 'sell'],
        RateType::NON_CASH->value => ['buyNC', 'sellNC'],
        RateType::CARD->value => ['buyArca', 'sellArca'],
    ];

    public function parse(string $html): array
    {
        $decoded = html_entity_decode($html);

        preg_match_all('/\{"currency":"[A-Z]{2,4}"[^{}]*\}/', $decoded, $matches);

        $rates = [];

        foreach ($matches[0] as $json) {
            $data = json_decode($json, true);

            if (! is_array($data) || empty($data['currency'])) {
                continue;
            }

            $code = strtoupper($data['currency']);

            foreach (self::RATE_TYPE_FIELDS as $rateType => [$buyKey, $sellKey]) {
                if (empty($data[$buyKey]) || empty($data[$sellKey])) {
                    continue;
                }

                $rates[] = [
                    'code' => $code,
                    'rate_type' => $rateType,
                    'buy' => (float) $data[$buyKey],
                    'sell' => (float) $data[$sellKey],
                ];
            }

            if (! empty($data['cbRate'])) {
                $rates[] = [
                    'code' => $code,
                    'rate_type' => RateType::CENTRAL_BANK->value,
                    'buy' => (float) $data['cbRate'],
                    'sell' => (float) $data['cbRate'],
                ];
            }
        }

        return $rates;
    }
}
