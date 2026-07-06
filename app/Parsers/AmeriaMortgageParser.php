<?php

namespace App\Parsers;

use App\Enums\MortgageRateType;

class AmeriaMortgageParser implements MortgageParser
{
    private const CURRENCY_ORDER = ['AMD', 'USD', 'EUR'];

    /**
     * Ameriabank's mortgage pages render their regulatory disclosure via a
     * DotNetNuke "Tabs" content module fetched over XHR - the marketing page
     * itself has no numbers in its static HTML. That module's JSON is
     * reachable directly with a plain HTTP GET (no JS engine needed): each
     * "slide" is one tab's disclosure text as an HTML blob. We match the
     * slide by its heading text rather than a fixed index, since slide order
     * isn't guaranteed and this same module lists other, unrelated products.
     */
    private const SLIDE_HEADING = 'Home Purchase Loan (secondary market)';

    public function parse(string $html): array
    {
        $data = json_decode($html, true);
        $slides = $data['data']['slides'] ?? [];

        $text = null;

        foreach ($slides as $slide) {
            $raw = $slide['description']['normalized'] ?? '';

            if (str_contains($raw, self::SLIDE_HEADING)) {
                $text = $this->toPlainText($raw);
                break;
            }
        }

        if ($text === null) {
            return [];
        }

        $amounts = $this->extractAmountsByCurrency($text);
        $term = $this->extractShortTermMonths($text);
        $rates = $this->extractPercentTriplet($text, '3.4. Nominal annual interest rate');
        $minDownPayment = $this->extractMinDownPayment($text);

        $offers = [];

        foreach (self::CURRENCY_ORDER as $index => $currency) {
            if (!isset($rates[$index])) {
                continue;
            }

            $offers[] = [
                'currency' => $currency,
                'rate_type' => MortgageRateType::FIXED->value,
                'category' => 'secondary_market',
                'rate_min' => $rates[$index],
                'rate_max' => $rates[$index],
                'term_min_months' => $term,
                'term_max_months' => $term,
                'min_down_payment_percent' => $minDownPayment,
                'min_amount' => $amounts[$currency][0] ?? null,
                'max_amount' => $amounts[$currency][1] ?? null,
            ];
        }

        return $offers;
    }

    private function toPlainText(string $html): string
    {
        $text = preg_replace('/<[^>]+>/', "\n", $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\x{00a0}/u', ' ', $text);

        return preg_replace('/\n\s*\n+/', "\n", $text);
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    private function extractAmountsByCurrency(string $text): array
    {
        $amounts = [];

        foreach (self::CURRENCY_ORDER as $currency) {
            if (preg_match('/' . $currency . '\s*([\d,]+)\s*-\s*' . $currency . '\s*([\d,]+)/', $text, $m)) {
                $amounts[$currency] = [
                    (float) str_replace(',', '', $m[1]),
                    (float) str_replace(',', '', $m[2]),
                ];
            }
        }

        return $amounts;
    }

    private function extractShortTermMonths(string $text): ?int
    {
        if (!preg_match('/3\.3\.\s*Term \(months\)\s*\n+3\.3\.1\.\s*(\d+)/', $text, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * @return array<int, float>
     */
    private function extractPercentTriplet(string $text, string $anchor, int $window = 600): array
    {
        $offset = strpos($text, $anchor);

        if ($offset === false) {
            return [];
        }

        preg_match_all('/(\d+(?:\.\d+)?)%/', substr($text, $offset, $window), $matches);

        return array_map('floatval', array_slice($matches[1], 0, 3));
    }

    private function extractMinDownPayment(string $text): ?float
    {
        if (!preg_match('/[Aa]t least (\d+(?:\.\d+)?)% of the purchase price/', $text, $m)) {
            return null;
        }

        return (float) $m[1];
    }
}
