<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-testing data only - not called from DatabaseSeeder::run(), so it
 * never lands in a production seed run. Creates a couple of tourism
 * partners with a fake telegram_chat_id (real enough to pass the "does a
 * partner serve this destination" check, but any real send to it will fail
 * against the live Telegram API - use the tourism:fake-reply command to
 * simulate a partner's reply instead of relying on a real Telegram round trip).
 *
 * Run with: php artisan db:seed --class=TourismDemoSeeder
 */
class TourismDemoSeeder extends Seeder
{
    public function run(): void
    {
        $partners = [
            [
                'slug' => 'demo-sunny-travel-co',
                'name' => 'Sunny Travel Co',
                'destinations' => ['GE', 'EG', 'AE'],
                'telegram_chat_id' => 'demo-chat-1',
            ],
            [
                'slug' => 'demo-blue-horizon-tours',
                'name' => 'Blue Horizon Tours',
                'destinations' => ['GE', 'GR', 'CY'],
                'telegram_chat_id' => 'demo-chat-2',
            ],
        ];

        foreach ($partners as $partner) {
            $organization = Organization::firstOrCreate(
                ['slug' => $partner['slug']],
                [
                    'name' => $partner['name'],
                    'type' => 'tourism',
                    'email' => $partner['slug'] . '@example.com',
                    'password' => Hash::make('password'),
                    'country_code' => 'AM',
                    'is_active' => true,
                    'telegram_chat_id' => $partner['telegram_chat_id'],
                ]
            );

            foreach ($partner['destinations'] as $countryCode) {
                $organization->tourismDestinations()->firstOrCreate(['country_code' => $countryCode]);
            }
        }

        $this->command?->info('Demo tourism partners ready: Sunny Travel Co (GE, EG, AE), Blue Horizon Tours (GE, GR, CY).');
    }
}
