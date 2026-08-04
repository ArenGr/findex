<?php

namespace Database\Seeders;

use App\Enums\RateType;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Called from DatabaseSeeder::run() - demo data, not production data, but
 * kept in the default run so `migrate:fresh --seed` produces a fully working
 * demo environment on its own. Creates a few exchange-office partners with
 * cash rates for USD/EUR/RUR plus a broader set (GBP/CHF/GEL/AED/CNY/KZT/
 * CAD/AUD, spread unevenly across partners like a real exchange market
 * would - not every office trades every currency), so:
 *  - the /exchange large-amount quote request flow has partners to match
 *    against (see Organization::exchangePartnersForCurrency()), and
 *  - the /rates and home-rates-table widgets have exchange-office rows to
 *    show alongside banks.
 *
 * These get a fake telegram_chat_id (same pattern as TourismDemoSeeder) so
 * they show as "connected" - a real send to them will fail, since it's not
 * a real chat id. Use `php artisan exchange:fake-reply` to simulate a
 * partner's response instead of relying on a live Telegram round trip.
 *
 * Can still be run alone: php artisan db:seed --class=ExchangeOrgSeeder
 */
class ExchangeOrgSeeder extends Seeder
{
    public function run(): void
    {
        $partners = [
            [
                'slug' => 'demo-yerevan-city-exchange',
                'name' => 'Yerevan City Exchange',
                'description' => 'A downtown exchange office with several branches near the central bank district, known for posting competitive cash rates.',
                'contact_phone' => '+374 10 600 100',
                'contact_whatsapp' => '+374 77 600 100',
                'telegram_chat_id' => 'demo-chat-101',
                'rates' => [
                    'USD' => [383.5, 388.0], 'EUR' => [413.0, 419.5], 'RUR' => [4.55, 4.78],
                    'GBP' => [484.0, 494.0], 'CHF' => [425.0, 433.0], 'GEL' => [137.0, 143.0], 'AED' => [102.5, 107.5],
                ],
            ],
            [
                'slug' => 'demo-golden-rate-exchange',
                'name' => 'Golden Rate Exchange',
                'description' => 'Family-run exchange office operating since the early 2000s, popular for negotiating on large-amount exchanges.',
                'contact_phone' => '+374 10 600 200',
                'contact_telegram' => 'goldenrate_am',
                'telegram_chat_id' => 'demo-chat-102',
                'rates' => [
                    'USD' => [384.0, 387.5], 'EUR' => [414.5, 420.0], 'RUR' => [4.58, 4.80],
                    'GBP' => [485.0, 493.0], 'CHF' => [426.0, 432.0], 'GEL' => [137.5, 142.5], 'CNY' => [52.5, 55.5],
                ],
            ],
            [
                'slug' => 'demo-capital-currency-house',
                'name' => 'Capital Currency House',
                'description' => 'A network of exchange kiosks across Yerevan malls and transit hubs, open extended hours.',
                'contact_phone' => '+374 10 600 300',
                'contact_whatsapp' => '+374 77 600 300',
                'contact_instagram' => 'capitalcurrencyhouse',
                'telegram_chat_id' => 'demo-chat-103',
                'rates' => [
                    'USD' => [383.0, 388.5], 'EUR' => [413.5, 421.0], 'RUR' => [4.52, 4.82],
                    'GBP' => [483.5, 494.5], 'CHF' => [424.5, 433.5], 'GEL' => [136.5, 143.5],
                    'AED' => [102.0, 108.0], 'CNY' => [52.0, 56.0], 'KZT' => [0.77, 0.83], 'AUD' => [250.0, 260.0],
                ],
            ],
            [
                'slug' => 'demo-swift-exchange-point',
                'name' => 'Swift Exchange Point',
                'description' => 'Focused on fast, no-appointment currency exchange, with same-day large-amount transactions.',
                'contact_phone' => '+374 10 600 400',
                'contact_telegram' => 'swiftexchange_am',
                'telegram_chat_id' => 'demo-chat-104',
                'rates' => [
                    'USD' => [384.5, 387.0], 'EUR' => [415.0, 418.5], 'RUR' => [4.60, 4.75],
                    'GBP' => [485.5, 492.5], 'CHF' => [426.5, 431.5], 'CAD' => [275.0, 285.0], 'AUD' => [249.5, 260.5],
                ],
            ],
            [
                'slug' => 'demo-northside-exchange',
                'name' => 'Northside Exchange',
                'description' => 'A neighborhood exchange office in northern Yerevan, known for consistent rates and no minimum transaction size.',
                'contact_phone' => '+374 10 600 500',
                'contact_whatsapp' => '+374 77 600 500',
                'telegram_chat_id' => 'demo-chat-105',
                'rates' => [
                    'USD' => [383.5, 388.0], 'EUR' => [414.0, 420.5], 'RUR' => [4.56, 4.79],
                    'GBP' => [484.5, 493.5], 'CHF' => [425.5, 432.5], 'GEL' => [137.2, 142.8],
                    'KZT' => [0.76, 0.84], 'CAD' => [274.5, 285.5],
                ],
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
                    'type' => 'exchange',
                    'country_code' => 'AM',
                    'is_active' => true,
                    'description_en' => $partner['description'],
                    'contact_phone' => $partner['contact_phone'] ?? null,
                    'contact_whatsapp' => $partner['contact_whatsapp'] ?? null,
                    'contact_telegram' => $partner['contact_telegram'] ?? null,
                    'contact_instagram' => $partner['contact_instagram'] ?? null,
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

            foreach ($partner['rates'] as $currencyCode => [$buyRate, $sellRate]) {
                $currency = Currency::where('code', $currencyCode)->first();

                if (!$currency) {
                    continue;
                }

                CurrencyRate::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'currency_id' => $currency->id,
                        'rate_type' => RateType::CASH,
                    ],
                    [
                        'buy_rate' => $buyRate,
                        'sell_rate' => $sellRate,
                        'scraped_at' => now(),
                    ]
                );
            }
        }

        $this->command?->info('Demo exchange office partners ready: ' . collect($partners)->pluck('name')->implode(', ') . '.');
    }
}
