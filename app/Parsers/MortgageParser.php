<?php

namespace App\Parsers;

interface MortgageParser
{
    /**
     * `apr_min`/`apr_max` (the published "actual" rate / փաստացի
     * տոկոսադրույք) and `source_tier` are optional: a parser that can reach
     * the APR - usually one page deeper than the headline nominal - should
     * return it, since the comparison ranks on APR when present. Omitting
     * them is fine; the offer then ranks on its nominal rate.
     *
     * @return array<int, array{
     *     currency: string,
     *     rate_type: string,
     *     category: string,
     *     rate_min: float,
     *     rate_max: float,
     *     apr_min?: ?float,
     *     apr_max?: ?float,
     *     term_min_months: ?int,
     *     term_max_months: ?int,
     *     min_down_payment_percent: ?float,
     *     min_amount: ?float,
     *     max_amount: ?float,
     *     source_tier?: ?string,
     * }>
     */
    public function parse(string $html): array;
}
