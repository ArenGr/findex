<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-testing data only - not called from DatabaseSeeder::run(), so it
 * never lands in a production seed run. Creates a couple of tourism
 * partners with a fake telegram_chat_id (real enough to pass the "does a
 * partner serve this destination" check, but any real send to it will fail
 * against the live Telegram API - use the tourism:fake-reply command to
 * simulate a partner submitting their secure response form instead of
 * relying on a real Telegram round trip).
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
            $email = $partner['slug'] . '@example.com';

            // Organization (business profile) and User (login, role=organization)
            // are two separate rows since the accounts-unification migration -
            // see RegisteredOrganizationController::store() for the same pattern.
            $organization = Organization::firstOrCreate(
                ['slug' => $partner['slug']],
                [
                    'name' => $partner['name'],
                    'type' => 'tourism',
                    'country_code' => 'AM',
                    'is_active' => true,
                    'telegram_chat_id' => $partner['telegram_chat_id'],
                ]
            );

            $user = User::firstOrNew(['email' => $email]);
            $user->name = $partner['name'];
            $user->password = Hash::make('password');
            $user->forceFill([
                'role' => UserRole::ORGANIZATION,
                'organization_id' => $organization->id,
                'email_verified_at' => now(),
            ])->save();

            foreach ($partner['destinations'] as $countryCode) {
                $organization->tourismDestinations()->firstOrCreate(['country_code' => $countryCode]);
            }
        }

        $this->command?->info('Demo tourism partners ready: Sunny Travel Co (GE, EG, AE), Blue Horizon Tours (GE, GR, CY).');
    }
}
