<?php

namespace App\Services\Insurance;

use App\Models\Organization;
use Illuminate\Contracts\Container\Container;

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

    // Whether this partner quotes for real, rather than through the mock.
    public function hasRealProvider(Organization $partner): bool
    {
        return isset(self::PROVIDERS[$partner->slug]);
    }
}
