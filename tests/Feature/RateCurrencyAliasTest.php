<?php

namespace Tests\Feature;

use App\Enums\CurrencyCode;
use App\Services\RateScraper;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the currency-code normalisation every scraped row passes through.
 *
 * The parser contract (see App\Parsers\RateParser) puts normalisation on the
 * caller, so a parser is entitled to emit whatever spelling its bank
 * publishes. RateScraper then folds that to a canonical code and drops
 * anything not in CurrencyCode::codes().
 *
 * The alias map used to hold only 'RUR' => 'RUR' - a no-op - while this app
 * canonicalises the ruble on RUR. Every bank publishing the ISO 'RUB'
 * (IDBank and AMIO among them) therefore had its ruble row discarded as an
 * untracked currency. It failed silently: the scrape still reported success,
 * one row lighter, and the comparison pages just showed no ruble.
 *
 * Reaching the private method by reflection is deliberate. It is the exact
 * unit that was wrong, and going through the public scrape() would mean
 * standing up Guzzle plus a fake bank page to assert the same thing.
 */
class RateCurrencyAliasTest extends TestCase
{
    private function normalize(string $code): string
    {
        $method = new ReflectionMethod(RateScraper::class, 'normalizeCurrencyCode');

        return $method->invoke(app(RateScraper::class), $code);
    }

    public static function rubleSpellings(): array
    {
        return [
            'ISO code' => ['RUB'],
            'legacy code' => ['RUR'],
            'lowercase' => ['rub'],
            'padded' => [' RUB '],
        ];
    }

    #[DataProvider('rubleSpellings')]
    public function test_every_ruble_spelling_normalises_to_the_tracked_code(string $published): void
    {
        $normalized = $this->normalize($published);

        $this->assertSame('RUR', $normalized);
        $this->assertContains(
            $normalized,
            CurrencyCode::codes(),
            "A bank publishing '{$published}' would have its ruble row dropped as untracked.",
        );
    }

    /**
     * The general form of the same bug: wherever this app's canonical code
     * differs from the ISO one it is named after, the ISO spelling has to be
     * aliased - otherwise any bank publishing it loses that currency.
     */
    public function test_every_currency_whose_canonical_code_differs_from_its_iso_name_is_aliased(): void
    {
        foreach (CurrencyCode::cases() as $currency) {
            if ($currency->name === $currency->value) {
                continue;
            }

            $this->assertSame(
                $currency->value,
                $this->normalize($currency->name),
                "{$currency->name} is stored as {$currency->value}, but a bank publishing "
                ."'{$currency->name}' would have that row dropped - add the alias.",
            );
        }
    }

    /**
     * Armswissbank quotes the offshore yuan. Same currency, different
     * market - and without the alias that bank simply shows no yuan.
     */
    public function test_the_offshore_yuan_is_stored_as_the_yuan(): void
    {
        $this->assertSame('CNY', $this->normalize('CNH'));
        $this->assertContains('CNY', CurrencyCode::codes());
    }

    public function test_an_untracked_currency_is_left_alone_rather_than_guessed_at(): void
    {
        $this->assertSame('JPY', $this->normalize('jpy'));
        $this->assertNotContains('JPY', CurrencyCode::codes());
    }
}
