<?php

namespace App\Parsers;

interface MortgageParser
{
    /**
     * @return array<int, array{
     *     currency: string,
     *     rate_type: string,
     *     category: string,
     *     rate_min: float,
     *     rate_max: float,
     *     term_min_months: ?int,
     *     term_max_months: ?int,
     *     min_down_payment_percent: ?float,
     *     min_amount: ?float,
     *     max_amount: ?float,
     * }>
     */
    public function parse(string $html): array;
}
