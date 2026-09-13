<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            $email = $partner['slug'].'@example.com';

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
