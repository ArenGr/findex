<?php

namespace App\Parsers;

use App\Enums\RateType;

class InecoRateParser implements RateParser
{
    /**
     * Inecobank's public pages sit behind a Cloudflare Managed Challenge -
     * a JS challenge no plain HTTP client can solve, which is why this
     * bank's source sat inactive. Its rates endpoint is not challenged and
     * answers a plain GET with JSON:
     *
     *   https://www.inecobank.am/api/rates
     *
     *   {"success":true,"items":[
     *      {"code":"USD",
     *       "cash":{"buy":362,"sell":367},
     *       "cashless":{"buy":362.5,"sell":367},
     *       "online":{"buy":362.73,"sell":367},
     *       "card":{"buy":362.5,"sell":368},
     *       "cb":{"buy":365.26,"sell":365.26}}, ...
     *   ]}
     *
     * Every category is present for every currency whether or not the bank
     * trades it that way; the ones it doesn't carry nulls rather than being
     * absent (GBP has no cash rate, XAU has nothing at all).
     */
    private const CATEGORIES = [
        'cash' => RateType::CASH,
        'cashless' => RateType::NON_CASH,
        'card' => RateType::CARD,
        'cb' => RateType::CENTRAL_BANK,
    ];

    /**
     * The endpoint also publishes an "online" rate - a better spread offered
     * inside the bank's own app. RateType has no case for it, and the
     * nearest ones mean something else (TRANSFER is wire transfers), so
     * filing it under one of those would misdescribe it on the comparison
     * page. Left out until the enum has somewhere honest to put it.
     */
    private const UNMAPPED_CATEGORY = 'online';

    public function parse(string $html): array
    {
        $data = json_decode($html, true);
        $items = $data['items'] ?? null;

        if (! is_array($items)) {
            return [];
        }

        $rates = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = $item['code'] ?? null;

            if (! is_string($code) || trim($code) === '') {
                continue;
            }

            foreach (self::CATEGORIES as $key => $rateType) {
                $buy = data_get($item, "{$key}.buy");
                $sell = data_get($item, "{$key}.sell");

                if (! is_numeric($buy) || ! is_numeric($sell)
                    || (float) $buy <= 0.0 || (float) $sell <= 0.0) {
                    continue;
                }

                $rates[] = [
                    'code' => strtoupper(trim($code)),
                    'rate_type' => $rateType->value,
                    'buy' => (float) $buy,
                    'sell' => (float) $sell,
                ];
            }
        }

        return $rates;
    }
}
