<?php

use App\Services\Cache\RateCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Backfills the three insurer logos now kept in public/images/organizations.
return new class extends Migration
{
    /** Slug => the file in public/images/organizations. */
    private const LOGOS = [
        'armenia-insurance' => '/images/organizations/armenia-insurance.png',
        'ingo-armenia' => '/images/organizations/ingo-armenia.svg',
        'liga-insurance' => '/images/organizations/liga-insurance.svg',
        'nairi-insurance' => '/images/organizations/nairi-insurance.svg',
        'rego-insurance' => '/images/organizations/rego-insurance.png',
        'sil-insurance' => '/images/organizations/sil-insurance.svg',
    ];

    private function flushRateCache(): void
    {
        RateCache::invalidate();
    }

    public function up(): void
    {
        foreach (self::LOGOS as $slug => $logo) {
            DB::table('organizations')
                ->where('slug', $slug)
                ->whereNull('logo')
                ->update(['logo' => $logo]);
        }

        $this->flushRateCache();
    }

    public function down(): void
    {
        foreach (self::LOGOS as $slug => $logo) {
            DB::table('organizations')
                ->where('slug', $slug)
                ->where('logo', $logo)
                ->update(['logo' => null]);
        }

        $this->flushRateCache();
    }
};
