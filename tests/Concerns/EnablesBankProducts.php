<?php

namespace Tests\Concerns;

use App\Http\Controllers\OfferController;
use App\Models\FeatureToggle;

/**
 * Bank product pages are off unless a feature_toggles row switches them on
 * (see FeatureToggle), and RefreshDatabase leaves that table empty - so any
 * test asserting a product page or its nav entry has to set the state it
 * expects rather than inheriting the seeder's defaults.
 */
trait EnablesBankProducts
{
    /**
     * @param  array<int, string>|null  $keys  null enables every known category
     */
    protected function enableBankProducts(?array $keys = null): void
    {
        $keys ??= OfferController::CATEGORIES;

        foreach (OfferController::CATEGORIES as $category) {
            FeatureToggle::updateOrCreate(
                ['key' => $category],
                ['is_enabled' => in_array($category, $keys, true)],
            );
        }

        // The enabled list is cached forever and only busted on write; the
        // array cache driver persists across requests within one test, so a
        // lookup made before this ran would otherwise stick.
        FeatureToggle::forgetCache();
    }
}
