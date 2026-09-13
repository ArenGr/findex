<?php

use App\Models\FeatureToggle;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds a row for every flag in App\Support\Features that has none yet.
 *
 * Existing rows are left alone, so the seven bank product pages keep whatever
 * an admin already set. Same operation as `features:sync`, run here so a deploy
 * needs no extra step.
 */
return new class extends Migration
{
    public function up(): void
    {
        FeatureToggle::sync();
        FeatureToggle::forgetCache();
    }

    public function down(): void
    {
        // Nothing: dropping rows would lose whatever an admin had set, and a
        // row nobody reads is harmless.
    }
};
