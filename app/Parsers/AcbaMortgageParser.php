<?php

namespace App\Parsers;

use App\Enums\MortgageRateType;

class AcbaMortgageParser implements MortgageParser
{
    private const CURRENCY_ORDER = ['AMD', 'EUR', 'USD'];

    private const TIER_PATTERNS = [
        'Fixed annual interest rate' => MortgageRateType::FIXED,
        'Annual floating interest rate \(Fixed years: 3\)' => MortgageRateType::FLOATING_3Y,
        'Annual floating interest rate \(Fixed years: 5\)' => MortgageRateType::FLOATING_5Y,
    ];

    private const RATE_SPAN_PATTERN = '/<span class="text-body-common font-semibold text-black lg:text-body-lg">\s*([\d.]+)(?:\s*-\s*([\d.]+))?%\s*<\/span>/';

    public function parse(string $html): array
    {
        $termRange = $this->extractTermRange($html);
        $amountRange = $this->extractAmountRange($html);
        $minDownPayment = $this->extractMinDownPayment($html);

        $offers = [];

        foreach (self::TIER_PATTERNS as $pattern => $rateType) {
            if (! preg_match('/'.$pattern.'/i', $html, $anchor, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $window = substr($html, $anchor[0][1], 3000);
            preg_match_all(self::RATE_SPAN_PATTERN, $window, $rates);

            if (count($rates[1]) !== count(self::CURRENCY_ORDER)) {
                throw new \RuntimeException(sprintf(
                    "ACBA mortgage page layout changed: expected %d rate columns for '%s', found %d",
                    count(self::CURRENCY_ORDER),
                    $pattern,
                    count($rates[1]),
                ));
            }

            foreach (self::CURRENCY_ORDER as $index => $currency) {
                if (! isset($rates[1][$index])) {
                    continue;
                }

                $min = (float) $rates[1][$index];
                $max = isset($rates[2][$index]) && $rates[2][$index] !== ''
                    ? (float) $rates[2][$index]
                    : $min;

                $offers[] = [
                    'currency' => $currency,
                    'rate_type' => $rateType->value,
                    'category' => 'secondary_market',
                    'rate_min' => $min,
                    'rate_max' => $max,
                    'term_min_months' => $termRange[0],
                    'term_max_months' => $termRange[1],
                    'min_down_payment_percent' => $minDownPayment,
                    'min_amount' => $currency === 'AMD' ? $amountRange[0] : null,
                    'max_amount' => $currency === 'AMD' ? $amountRange[1] : null,
                ];
            }
        }

        return $offers;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function extractTermRange(string $html): array
    {
        if (! preg_match('/(\d+)\s*[\x{2013}\x{2014}-]\s*(\d+)\s*months/u', $html, $m)) {
            return [null, null];
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function extractAmountRange(string $html): array
    {
        if (! preg_match('/AMD\s*([\d,]+)\s*[\x{2013}\x{2014}-]\s*([\d,]+)/u', $html, $m)) {
            return [null, null];
        }

        return [
            (float) str_replace(',', '', $m[1]),
            (float) str_replace(',', '', $m[2]),
        ];
    }

    private function extractMinDownPayment(string $html): ?float
    {
        if (! preg_match('/minimum prepayment is set at (?:at least )?(\d+(?:\.\d+)?)%/i', $html, $m)) {
            return null;
        }

        return (float) $m[1];
    }
}
