<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationSource;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create currencies - kept in sync with App\Enums\CurrencyCode,
        // the single source of truth for which currencies we track.
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF'],
            ['code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => '₽'],
            ['code' => 'GEL', 'name' => 'Georgian Lari', 'symbol' => '₾'],
        ];

        foreach ($currencies as $index => $currency) {
            Currency::firstOrCreate(['code' => $currency['code']], [...$currency, 'sort_order' => $index + 1]);
        }

        // Create organizations
        $acba = Organization::firstOrCreate(
            ['slug' => 'acba'],
            [
                'name' => 'ACBA Bank',
                'type' => 'bank',
                'website' => 'https://www.acba.am',
                'logo' => '/images/organizations/acba.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $ineco = Organization::firstOrCreate(
            ['slug' => 'ineco'],
            [
                'name' => 'Inecobank',
                'type' => 'bank',
                'website' => 'https://www.inecobank.am',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $ameria = Organization::firstOrCreate(
            ['slug' => 'ameria'],
            [
                'name' => 'Ameriabank',
                'type' => 'bank',
                'website' => 'https://ameriabank.am',
                'logo' => '/images/organizations/ameria.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $unibank = Organization::firstOrCreate(
            ['slug' => 'unibank'],
            [
                'name' => 'Unibank',
                'type' => 'bank',
                'website' => 'https://www.unibank.am',
                'logo' => '/images/organizations/unibank.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $evoca = Organization::firstOrCreate(
            ['slug' => 'evoca'],
            [
                'name' => 'Evocabank',
                'type' => 'bank',
                'website' => 'https://www.evoca.am',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        // Create organization sources
        OrganizationSource::updateOrCreate(
            ['organization_id' => $acba->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $acba->id, 'source_type' => 'deposits'],
            [
                'url' => '/hy/deposits',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $acba->id, 'source_type' => 'loans'],
            [
                'url' => '/hy/loans',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $acba->id, 'source_type' => 'mortgages'],
            [
                'url' => 'https://acba.am/en/individual/loan/161',
                'is_active' => true,
            ]
        );

        // Inactive: Inecobank sits behind a Cloudflare Managed Challenge
        // (a JS challenge, not just a cookie check) that a plain HTTP client
        // cannot solve. Left registered for whenever that gets tackled -
        // flip is_active back on once there's a working fetch path.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $ineco->id, 'source_type' => 'currency_rates'],
            [
                'url' => 'https://www.inecobank.am/en/Individual',
                'is_active' => false,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $ineco->id, 'source_type' => 'deposits'],
            [
                'url' => '/deposits',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $ineco->id, 'source_type' => 'loans'],
            [
                'url' => '/loans',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $ameria->id, 'source_type' => 'currency_rates'],
            [
                'url' => 'https://ameriabank.am/en/',
                'is_active' => true,
            ]
        );

        // Points directly at the JSON content-module endpoint the mortgage
        // page's disclosure tab loads via XHR - the page itself is JS-hydrated
        // and Guzzle wouldn't see the numbers, but this endpoint is a plain,
        // stable HTTP GET with no session/JS required.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $ameria->id, 'source_type' => 'mortgages'],
            [
                'url' => 'https://ameriabank.am/en/API/WebsitesCreative/MyContentManager/API/Init?portalId=0&tabId=6119&moduleId=20719',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $unibank->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $evoca->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en',
                'is_active' => true,
            ]
        );

        $this->command->info('Organizations and sources seeded successfully!');
    }
}
