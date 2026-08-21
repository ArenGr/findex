<?php

namespace App\Parsers;

use App\Enums\RateType;

class FastBankRateParser implements RateParser
{
    /**
     * Fast Bank renders no rates server-side - its page ships the labels
     * ("buy":"Buy") and fetches the figures in the browser. This parser
     * reads that same endpoint:
     *
     *   /api/exchange-rates?kind=rates&payType=
     *
     *   {"Rates":[{"Id":"USD","Buy":362.5,"Sale":365.5,"Unit":1,
     *              "PayType":1,"PrevBuy":...,"PrevSale":...}, ...],
     *    "ResultCode":..., "ResultMessage":"..."}
     *
     * The page requests one pay type at a time (payType=cash|noncash|card).
     * Leaving the parameter empty returns all three in a single response,
     * which is what the source URL does - one fetch per scrape rather than
     * three.
     *
     * NOTE: this endpoint is under /api/, which the bank's robots.txt
     * disallows. Scraping it anyway is a deliberate decision by the site
     * owner, recorded here so it is not mistaken for an oversight.
     */
    private const PAY_TYPES = [
        // Not the ordering you would guess - 0 is the non-cash rate, not the
        // cash one. Confirmed against the page's own named requests: the USD
        // spread returned for payType=noncash is the one carried by PayType
        // 0, cash by 1, card by 5.
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

        // An unknown pay type is left out rather than guessed at - filing it
        // under the wrong one would misdescribe the rate on the comparison
        // page, which is worse than the currency simply not appearing.
        if (! is_int($payType) || ! isset(self::PAY_TYPES[$payType])) {
            return null;
        }

        $buy = $row['Buy'] ?? null;
        $sell = $row['Sale'] ?? null;

        if (! is_numeric($buy) || ! is_numeric($sell)
            || (float) $buy <= 0.0 || (float) $sell <= 0.0) {
            return null;
        }

        // Rates are quoted per `Unit` of the currency, and the bank's own
        // page divides by it before displaying anything. Every unit is 1
        // today, but a currency quoted per 100 (as several banks do with the
        // ruble and the tenge) would otherwise be published a hundredfold
        // too high.
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
