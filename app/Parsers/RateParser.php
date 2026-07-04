<?php

namespace App\Parsers;

interface RateParser
{
    /**
     * Parse raw HTML into a normalized list of currency rates.
     *
     * A currency can appear multiple times, once per rate type (cash, card,
     * transfer, etc - see App\Enums\RateType). Each row is:
     * ['code' => 'USD', 'rate_type' => RateType::CASH->value, 'buy' => 365.0, 'sell' => 370.0].
     * Currency-code normalization (e.g. RUR -> RUB) is handled by the caller.
     *
     * @return array<int, array{code: string, rate_type: string, buy: float, sell: float}>
     */
    public function parse(string $html): array;
}
