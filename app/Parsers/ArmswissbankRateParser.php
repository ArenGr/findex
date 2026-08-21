<?php

namespace App\Parsers;

use App\Enums\RateType;

class ArmswissbankRateParser implements RateParser
{
    /**
     * Armswissbank's homepage ships the rates table as an empty skeleton -
     * every cell is a literal "-" with an id like bid_1/offer_1 - and fills
     * it from an AJAX endpoint. This parser reads that endpoint:
     *
     *   https://www.armswissbank.am/include/ajax.php
     *
     *   {"lmasbrate":[
     *      {"ISO":"USD","BID":"363.00","OFFER":"367.00",
     *       "BID_cash":"363.00","OFFER_cash":"367.00","hert":"1"}, ...
     *   ], ...}
     *
     * The page POSTs to it; a plain GET returns the identical body, so this
     * stays within the scraper's GET-only fetch.
     *
     * The rows also carry `hert`, the index tying a row to its table cell.
     * It is ignored - `ISO` names the currency outright, so there is no need
     * to depend on the page's row ordering.
     *
     * The response holds other market data too (gold, LIBOR, oil, key
     * rates); only lmasbrate is read.
     */
    private const CATEGORIES = [
        RateType::NON_CASH->value => ['BID', 'OFFER'],
        RateType::CASH->value => ['BID_cash', 'OFFER_cash'],
    ];

    public function parse(string $html): array
    {
        $data = json_decode($html, true);
        $rows = $data['lmasbrate'] ?? null;

        if (! is_array($rows)) {
            return [];
        }

        $rates = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = $row['ISO'] ?? null;

            if (! is_string($code) || trim($code) === '') {
                continue;
            }

            foreach (self::CATEGORIES as $rateType => [$buyKey, $sellKey]) {
                $buy = $row[$buyKey] ?? null;
                $sell = $row[$sellKey] ?? null;

                // The bank publishes "0.00" for a currency it does not trade
                // in that form - SEK, CNH and AED are non-cash only - and
                // the page itself renders those as a dash. Storing the zero
                // would put a 0 in the comparison table instead of leaving
                // the cell empty.
                if (! is_numeric($buy) || ! is_numeric($sell)
                    || (float) $buy <= 0.0 || (float) $sell <= 0.0) {
                    continue;
                }

                $rates[] = [
                    'code' => strtoupper(trim($code)),
                    'rate_type' => $rateType,
                    'buy' => (float) $buy,
                    'sell' => (float) $sell,
                ];
            }
        }

        return $rates;
    }
}
