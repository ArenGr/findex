<?php

namespace App\Parsers;

use App\Enums\RateType;

class ConverseRateParser implements RateParser
{
    /**
     * Converse's site is a React app that renders nothing server-side, so
     * there is no HTML table to read. It fills itself from the bank's own
     * public JSON API, which this parser reads instead:
     *
     *   https://sapi.conversebank.am/api/v2/currencyrates
     *
     * The response is an object keyed by the bank's own rate-group names,
     * each holding rows shaped like:
     *
     *   {"buy":421,"sell":431,"type":"Non Cash","iso2":"AMD",
     *    "rate_date":"...","currency":{"iso":"EUR", ...}}
     *
     * Despite the name, `iso2` is NOT this row's currency - `currency.iso`
     * is. See the QUOTE_CURRENCY note below.
     */
    private const CATEGORIES = [
        'Cash' => RateType::CASH,
        'Non Cash' => RateType::NON_CASH,
        'Card' => RateType::CARD,
    ];

    /**
     * `iso2` is the currency the row is *quoted in*, and it is not always
     * AMD. The "Card" group is mostly cross rates for card payments made
     * abroad - EUR quoted in RUB, CNY quoted in EUR - sitting in the same
     * list as the AMD-quoted ones and distinguished only by this field:
     *
     *   {"buy":92.7, "iso2":"RUB", "currency":{"iso":"EUR"}}   1 EUR = 92.7 RUB
     *   {"buy":421,  "iso2":"AMD", "currency":{"iso":"EUR"}}   1 EUR = 421 AMD
     *
     * This app stores no quote currency - currency_rates has currency_id and
     * a buy/sell pair, and every rate in it is understood to be against the
     * dram. Taking the first row above would therefore publish EUR at 92.7
     * instead of 421: not a dropped row, a wrong one, shown to users as a
     * real quote. So anything not quoted in AMD is skipped here.
     */
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
     * The API also publishes a "Metal" group (gold, XAU). It is deliberately
     * absent from CATEGORIES: this table compares currencies, and a gram of
     * gold is not one.
     *
     * @param  array<string, mixed>  $row
     * @return array{code: string, rate_type: string, buy: float, sell: float}|null
     */
    private function buildRate(array $row, RateType $rateType): ?array
    {
        $code = data_get($row, 'currency.iso');

        // Some rows carry `"currency": null` - a currency the bank has since
        // retired, still quoted. Without the nested object there is nothing
        // naming it, and `iso2` names the other side of the pair, so there
        // is no way to tell what is being priced. Guessing would mislabel a
        // live rate, so it is dropped.
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

        // A zero on either side means "not traded today" rather than a free
        // currency, and would render as a 0 column on the comparison page.
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
