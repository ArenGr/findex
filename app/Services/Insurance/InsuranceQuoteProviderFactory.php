<?php

namespace App\Services\Insurance;

use App\Models\Organization;
use Illuminate\Contracts\Container\Container;

/**
 * Picks the provider that answers for a given insurer, keyed on the
 * organization's slug - the same per-partner adapter arrangement
 * RateParserFactory uses for the banks.
 *
 * Anything without an entry here falls back to MockInsuranceProvider, so an
 * insurer can be listed on the site and compared against long before it has
 * an integration. That fallback is the reason this returns a provider for
 * every partner rather than filtering the list: a missing integration should
 * read as "we have not wired this one up yet", not as a partner that
 * silently vanishes from the comparison.
 */
class InsuranceQuoteProviderFactory
{
    /**
     * @var array<string, class-string<InsuranceQuoteProviderInterface>>
     */
    private const PROVIDERS = [
        IngoAppaProvider::ORGANIZATION_SLUG => IngoAppaProvider::class,
        ArmeniaInsuranceProvider::ORGANIZATION_SLUG => ArmeniaInsuranceProvider::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(Organization $partner): InsuranceQuoteProviderInterface
    {
        $provider = self::PROVIDERS[$partner->slug] ?? MockInsuranceProvider::class;

        return $this->container->make($provider);
    }

    /**
     * Whether this partner quotes for real, rather than through the mock.
     * Used by the results page to say so, and by tests to assert that a
     * newly added provider is actually reachable by slug - a typo there
     * would otherwise degrade silently to invented prices.
     */
    public function hasRealProvider(Organization $partner): bool
    {
        return isset(self::PROVIDERS[$partner->slug]);
    }
}
