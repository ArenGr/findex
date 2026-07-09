<?php

namespace App\Parsers;

use App\Enums\RateType;

class ArmeconombankRateParser implements RateParser
{
    /**
     * Map of RateType => [buy field, sell field] in AEB's JSON payload.
     */
    private const RATE_TYPE_FIELDS = [
        RateType::CASH->value => ['buy', 'sell'],
        RateType::NON_CASH->value => ['buyNC', 'sellNC'],
        RateType::CARD->value => ['buyArca', 'sellArca'],
    ];

    /**
     * AEB's homepage embeds its exchange rates as HTML-entity-escaped JSON,
     * one object per currency:
     *
     *   {&quot;currency&quot;:&quot;USD&quot;,&quot;buy&quot;:&quot;365&quot;,
     *    &quot;sell&quot;:&quot;370&quot;,&quot;cbRate&quot;:&quot;367.29&quot;,
     *    &quot;buyNC&quot;:&quot;365&quot;,&quot;sellNC&quot;:&quot;370&quot;,
     *    &quot;sellArca&quot;:&quot;373&quot;,&quot;buyArca&quot;:&quot;363&quot;, ...}
     *
     * ("Arca" is the Armenian national card payment system - its own
     * buy/sell spread is this bank's "card" rate type.) A currency without a
     * given rate type reports "0" or an empty string for it rather than
     * omitting the fields, so both are filtered out here.
     */
    public function parse(string $html): array
    {
        $decoded = html_entity_decode($html);

        preg_match_all('/\{"currency":"[A-Z]{2,4}"[^{}]*\}/', $decoded, $matches);

        $rates = [];

        foreach ($matches[0] as $json) {
            $data = json_decode($json, true);

            if (!is_array($data) || empty($data['currency'])) {
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

            if (!empty($data['cbRate'])) {
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
