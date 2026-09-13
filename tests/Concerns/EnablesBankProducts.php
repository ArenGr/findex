<?php

namespace Tests\Concerns;

use App\Http\Controllers\OfferController;
use App\Models\FeatureToggle;

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

        FeatureToggle::forgetCache();
    }
}
