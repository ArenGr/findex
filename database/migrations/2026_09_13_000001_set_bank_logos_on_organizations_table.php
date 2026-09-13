<?php

use App\Services\Cache\RateCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Backfills the fourteen bank logos now kept in public/images/organizations.
return new class extends Migration
{
    /** Slug => the file in public/images/organizations. */
    private const LOGOS = [
        'aeb' => '/images/organizations/aeb.svg',
        'amio' => '/images/organizations/amio.svg',
        'araratbank' => '/images/organizations/araratbank.png',
        'ardshinbank' => '/images/organizations/ardshinbank.svg',
        'armswissbank' => '/images/organizations/armswissbank.png',
        'artsakhbank' => '/images/organizations/artsakhbank.svg',
        'byblos' => '/images/organizations/byblos.svg',
        'conversebank' => '/images/organizations/conversebank.svg',
        'evoca' => '/images/organizations/evoca.png',
        'fastbank' => '/images/organizations/fastbank.png',
        'idbank' => '/images/organizations/idbank.svg',
        'ineco' => '/images/organizations/ineco.svg',
        'mellat' => '/images/organizations/mellat.svg',
        'vtb' => '/images/organizations/vtb.svg',
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
