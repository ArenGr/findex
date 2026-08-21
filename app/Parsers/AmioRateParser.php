<?php

namespace App\Parsers;

use App\Enums\RateType;

class AmioRateParser implements RateParser
{
    /**
     * AMIO's exchange page is a Next.js app, so the rendered table is built
     * in the browser and there is no HTML table to read. The data is still
     * served with the page though: Next.js embeds the props it hydrated from
     * in a <script id="__NEXT_DATA__"> block, and the rates sit inside it.
     *
     * Reading that JSON is both easier and sturdier than scraping a table -
     * a redesign changes the markup constantly, but this shape belongs to
     * the bank's own data layer and moves far less often.
     *
     *   props.pageProps.data.rates.cash = [
     *       {"currency":"USD","buyValue":"362.50","sellValue":"367.50",
     *        "isCash":true,"branchIndex":"00", ...}, ...
     *   ]
     *   props.pageProps.data.rates.card = [ ... same shape, isCash:false ]
     *
     * "card" is the bank's own wording for its non-cash rate, which is what
     * RateType::NON_CASH means here - the same distinction every other
     * parser in this directory draws.
     */
    private const CATEGORIES = [
        'cash' => RateType::CASH,
        'card' => RateType::NON_CASH,
    ];

    /**
     * Rates are published per branch. Only the head office ("00") is taken:
     * the comparison pages show one rate per bank, and mixing branches would
     * silently make a bank look better or worse than it quotes anywhere.
     */
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

        // A zero on either side means the bank is not quoting that currency
        // right now, not that it trades at nothing.
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
