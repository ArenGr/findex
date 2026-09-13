<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Organization;
use App\Models\OrganizationSource;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    // Run the database seeds.
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF'],
            ['code' => 'RUR', 'name' => 'Russian Ruble', 'symbol' => '₽'],
            ['code' => 'GEL', 'name' => 'Georgian Lari', 'symbol' => '₾'],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
            ['code' => 'KZT', 'name' => 'Kazakhstani Tenge', 'symbol' => '₸'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'CA$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
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
                'logo' => '/images/organizations/ineco.svg',
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
                'logo' => '/images/organizations/evoca.png',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $araratbank = Organization::firstOrCreate(
            ['slug' => 'araratbank'],
            [
                'name' => 'AraratBank',
                'type' => 'bank',
                'website' => 'https://www.araratbank.am',
                'logo' => '/images/organizations/araratbank.png',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $aeb = Organization::firstOrCreate(
            ['slug' => 'aeb'],
            [
                'name' => 'Armeconombank',
                'type' => 'bank',
                'website' => 'https://www.aeb.am',
                'logo' => '/images/organizations/aeb.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $vtb = Organization::firstOrCreate(
            ['slug' => 'vtb'],
            [
                'name' => 'VTB Bank (Armenia)',
                'type' => 'bank',
                'website' => 'https://www.vtb.am',
                'logo' => '/images/organizations/vtb.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $idbank = Organization::firstOrCreate(
            ['slug' => 'idbank'],
            [
                'name' => 'IDBank',
                'type' => 'bank',
                'website' => 'https://www.idbank.am',
                'logo' => '/images/organizations/idbank.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $amio = Organization::firstOrCreate(
            ['slug' => 'amio'],
            [
                'name' => 'AMIO Bank',
                'type' => 'bank',
                'website' => 'https://www.amiobank.am',
                'logo' => '/images/organizations/amio.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $ardshinbank = Organization::firstOrCreate(
            ['slug' => 'ardshinbank'],
            [
                'name' => 'Ardshinbank',
                'type' => 'bank',
                'website' => 'https://ardshinbank.am',
                'logo' => '/images/organizations/ardshinbank.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $armswissbank = Organization::firstOrCreate(
            ['slug' => 'armswissbank'],
            [
                'name' => 'Armswissbank',
                'type' => 'bank',
                'website' => 'https://www.armswissbank.am',
                'logo' => '/images/organizations/armswissbank.png',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $mellat = Organization::firstOrCreate(
            ['slug' => 'mellat'],
            [
                'name' => 'Mellat Bank',
                'type' => 'bank',
                'website' => 'https://mellatbank.am',
                'logo' => '/images/organizations/mellat.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $byblos = Organization::firstOrCreate(
            ['slug' => 'byblos'],
            [
                'name' => 'Byblos Bank Armenia',
                'type' => 'bank',
                'website' => 'https://www.byblosbankarmenia.am',
                'logo' => '/images/organizations/byblos.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $fastbank = Organization::firstOrCreate(
            ['slug' => 'fastbank'],
            [
                'name' => 'Fast Bank',
                'type' => 'bank',
                'website' => 'https://www.fastbank.am',
                'logo' => '/images/organizations/fastbank.png',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $conversebank = Organization::firstOrCreate(
            ['slug' => 'conversebank'],
            [
                'name' => 'Converse Bank',
                'type' => 'bank',
                'website' => 'https://www.conversebank.am',
                'logo' => '/images/organizations/conversebank.svg',
                'country_code' => 'AM',
                'is_active' => true,
            ]
        );

        $artsakhbank = Organization::firstOrCreate(
            ['slug' => 'artsakhbank'],
            [
                'name' => 'Artsakhbank',
                'type' => 'bank',
                'website' => 'https://www.artsakhbank.am',
                'logo' => '/images/organizations/artsakhbank.svg',
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

        OrganizationSource::updateOrCreate(
            ['organization_id' => $ineco->id, 'source_type' => 'currency_rates'],
            [
                'url' => 'https://www.inecobank.am/api/rates',
                'is_active' => true,
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

        OrganizationSource::updateOrCreate(
            ['organization_id' => $araratbank->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $aeb->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $vtb->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en/currency',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $idbank->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en/rates/',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $artsakhbank->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en/exchange-rates',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $amio->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/exchanges',
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $conversebank->id, 'source_type' => 'currency_rates'],
            [
                'url' => 'https://sapi.conversebank.am/api/v2/currencyrates',
                'is_active' => true,
            ]
        );

        // Nuxt app; the page renders no rate table and calls this itself.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $ardshinbank->id, 'source_type' => 'currency_rates'],
            ['url' => 'https://ardshinbank.am/api/currency', 'is_active' => true]
        );

        // The homepage table ships as an empty skeleton filled from this endpoint.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $armswissbank->id, 'source_type' => 'currency_rates'],
            ['url' => 'https://www.armswissbank.am/include/ajax.php', 'is_active' => true]
        );

        // Angular app - the served HTML is a shell with no rates in it.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $mellat->id, 'source_type' => 'currency_rates'],
            ['url' => 'https://api.mellatbank.am/api/v1/rate/list', 'is_active' => true]
        );

        // Server-rendered, unlike the rest of this batch.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $byblos->id, 'source_type' => 'currency_rates'],
            ['url' => '/en', 'is_active' => true]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $fastbank->id, 'source_type' => 'currency_rates'],
            ['url' => 'https://www.fastbank.am/api/exchange-rates?kind=rates&payType=', 'is_active' => true]
        );

        // Branch listings.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $acba->id, 'source_type' => 'branches'],
            ['url' => 'https://www.acba.am/en/branches', 'is_active' => true]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $amio->id, 'source_type' => 'branches'],
            ['url' => 'https://www.amiobank.am/en/offices', 'is_active' => true]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $araratbank->id, 'source_type' => 'branches'],
            ['url' => 'https://www.araratbank.am/en/branches', 'is_active' => true]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $aeb->id, 'source_type' => 'branches'],
            [
                'url' => 'https://www.aeb.am/en/branch-service-network/ajax',
                // Without this the endpoint serves a 404 page: it answers only what it considers an AJAX request.
                'request_headers' => ['X-Requested-With' => 'XMLHttpRequest'],
                'is_active' => true,
            ]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $artsakhbank->id, 'source_type' => 'branches'],
            ['url' => 'https://www.artsakhbank.am/en/map-and-branches', 'is_active' => true]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $evoca->id, 'source_type' => 'branches'],
            ['url' => 'https://www.evoca.am/en/branches-and-atms/', 'is_active' => true]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $unibank->id, 'source_type' => 'branches'],
            ['url' => 'https://www.unibank.am/en/branch/', 'is_active' => true]
        );

        OrganizationSource::updateOrCreate(
            ['organization_id' => $conversebank->id, 'source_type' => 'branches'],
            ['url' => 'https://sapi.conversebank.am/api/v2/branches', 'is_active' => true]
        );

        $insurers = [
            ['slug' => 'ingo-armenia', 'name' => 'INGO Armenia', 'website' => 'https://ingoarmenia.am', 'logo' => '/images/organizations/ingo-armenia.svg'],
            ['slug' => 'armenia-insurance', 'name' => 'Armenia Insurance', 'website' => 'https://armeniainsurance.am', 'logo' => '/images/organizations/armenia-insurance.png'],
            ['slug' => 'nairi-insurance', 'name' => 'Nairi Insurance', 'website' => 'https://nairi-insurance.am', 'logo' => '/images/organizations/nairi-insurance.svg'],
            ['slug' => 'liga-insurance', 'name' => 'Liga Insurance', 'website' => 'https://liga.am', 'logo' => '/images/organizations/liga-insurance.svg'],
            ['slug' => 'sil-insurance', 'name' => 'Sil Insurance', 'website' => 'https://silinsurance.am', 'logo' => '/images/organizations/sil-insurance.svg'],
            ['slug' => 'rego-insurance', 'name' => 'REGO Insurance', 'website' => 'https://regoinsurance.am', 'logo' => '/images/organizations/rego-insurance.png'],
        ];

        foreach ($insurers as $insurer) {
            Organization::firstOrCreate(
                ['slug' => $insurer['slug']],
                [
                    'name' => $insurer['name'],
                    'type' => 'insurance',
                    'website' => $insurer['website'],
                    'logo' => $insurer['logo'] ?? null,
                    'country_code' => 'AM',
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Organizations and sources seeded successfully!');
    }
}
