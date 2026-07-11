<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-testing data only - not called from DatabaseSeeder::run(), so it
 * never lands in a production seed run. Creates a few auto insurance
 * partners so the /insurance/auto request/results flow can be demoed:
 * unlike tourism partners, these don't need a telegram_chat_id - quotes
 * come from MockInsuranceProvider (see AutoInsuranceQuoteService), standing
 * in for the real per-partner APIs these organizations will eventually
 * provide.
 *
 * Run with: php artisan db:seed --class=AutoInsuranceDemoSeeder
 */
class AutoInsuranceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $partners = [
            ['slug' => 'demo-safedrive-insurance', 'name' => 'SafeDrive Insurance'],
            ['slug' => 'demo-ararat-insurance-co', 'name' => 'Ararat Insurance Co'],
            ['slug' => 'demo-armenia-auto-cover', 'name' => 'Armenia Auto Cover'],
        ];

        foreach ($partners as $partner) {
            Organization::firstOrCreate(
                ['slug' => $partner['slug']],
                [
                    'name' => $partner['name'],
                    'type' => 'insurance',
                    'email' => $partner['slug'] . '@example.com',
                    'password' => Hash::make('password'),
                    'country_code' => 'AM',
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Demo auto insurance partners ready: SafeDrive Insurance, Ararat Insurance Co, Armenia Auto Cover.');
    }
}
