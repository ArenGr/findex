<?php

namespace App\Parsers;

interface RateParser
{
    /**
     * Parse raw HTML into a normalized list of currency rates.
     *
     * @return array<int, array{code: string, rate_type: string, buy: float, sell: float}>
     */
    public function parse(string $html): array;
}
