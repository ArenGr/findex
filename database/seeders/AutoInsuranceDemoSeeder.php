<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
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
        // Contact info and a description are filled in on every partner
        // (real orgs often leave these blank) so the results page always
        // shows contact pills and a full profile - this seeder's purpose is
        // a convincing walkthrough for prospective insurer partners, not a
        // realistic snapshot of partial onboarding.
        $partners = [
            [
                'slug' => 'demo-safedrive-insurance',
                'name' => 'SafeDrive Insurance',
                'description' => 'One of Armenia\'s longest-standing motor insurers, known for a wide branch network and fast in-person claims handling.',
                'contact_phone' => '+374 10 500 100',
                'contact_whatsapp' => '+374 77 500 100',
                'contact_telegram' => 'safedrive_am',
            ],
            [
                'slug' => 'demo-ararat-insurance-co',
                'name' => 'Ararat Insurance Co',
                'description' => 'A digital-first insurer offering instant e-policies and a mobile app for managing claims end to end.',
                'contact_phone' => '+374 10 500 200',
                'contact_whatsapp' => '+374 77 500 200',
                'contact_instagram' => 'araratinsurance',
            ],
            [
                'slug' => 'demo-armenia-auto-cover',
                'name' => 'Armenia Auto Cover',
                'description' => 'Specializes in motor insurance only, which keeps rates competitive and claims handling focused.',
                'contact_phone' => '+374 10 500 300',
                'contact_telegram' => 'armeniaautocover',
            ],
            [
                'slug' => 'demo-horizon-insurance-group',
                'name' => 'Horizon Insurance Group',
                'description' => 'Part of a regional insurance group, backing every policy with strong reinsurance coverage.',
                'contact_phone' => '+374 10 500 400',
                'contact_whatsapp' => '+374 77 500 400',
                'contact_instagram' => 'horizoninsurance.am',
            ],
            [
                'slug' => 'demo-metropol-insurance',
                'name' => 'Metropol Insurance',
                'description' => 'A Yerevan-based insurer built around dedicated claims managers for every policyholder.',
                'contact_phone' => '+374 10 500 500',
                'contact_telegram' => 'metropolinsurance',
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
                    'type' => 'insurance',
                    'country_code' => 'AM',
                    'is_active' => true,
                    'description_en' => $partner['description'],
                    'contact_phone' => $partner['contact_phone'] ?? null,
                    'contact_whatsapp' => $partner['contact_whatsapp'] ?? null,
                    'contact_telegram' => $partner['contact_telegram'] ?? null,
                    'contact_instagram' => $partner['contact_instagram'] ?? null,
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
        }

        $this->command?->info('Demo auto insurance partners ready: ' . collect($partners)->pluck('name')->implode(', ') . '.');
    }
}
