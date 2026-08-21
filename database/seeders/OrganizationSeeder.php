<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Organization;
use App\Models\OrganizationSource;
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

        $araratbank = Organization::firstOrCreate(
            ['slug' => 'araratbank'],
            [
                'name' => 'AraratBank',
                'type' => 'bank',
                'website' => 'https://www.araratbank.am',
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

        // Inecobank's pages sit behind a Cloudflare Managed Challenge that a
        // plain HTTP client cannot solve - /en/Individual answers 403, which
        // is why this source was inactive. Its rates endpoint is not
        // challenged and serves the same figures as JSON, so that is what is
        // scraped now. (robots.txt is challenged too and cannot be read by
        // any non-browser client; unreadable is not a disallow, and this is
        // a public unauthenticated endpoint the bank's own site calls.)
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

        // The homepage embeds the full rate table (all rows, including ones
        // hidden behind a "See more" toggle) as static server-rendered HTML.
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

        // Both rate-type tabs on this page are present in the static HTML
        // (Bootstrap tabs toggled with CSS/JS, not fetched via AJAX).
        OrganizationSource::updateOrCreate(
            ['organization_id' => $vtb->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/en/currency',
                'is_active' => true,
            ]
        );

        // The dedicated /rates/ page (rather than the homepage widget) is
        // used since it's the same markup with no other benefit either way,
        // but is the page IDBank's own "All rates" link points to.
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

        // AMIO renders its rates client-side, so this page carries no rate
        // markup - AmioRateParser reads the Next.js hydration payload the
        // page ships to do that rendering. Locale-less on purpose: the URL
        // is the same for every language and the payload is not translated.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $amio->id, 'source_type' => 'currency_rates'],
            [
                'url' => '/exchanges',
                'is_active' => true,
            ]
        );

        // Absolute, and on a different host than the website: conversebank.am
        // is a React app that renders no rates at all, and this is the API it
        // calls to fill itself. See ConverseRateParser.
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

        // The homepage table ships as an empty skeleton filled from this
        // endpoint. The page POSTs to it; GET returns the same body.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $armswissbank->id, 'source_type' => 'currency_rates'],
            ['url' => 'https://www.armswissbank.am/include/ajax.php', 'is_active' => true]
        );

        // Angular app - the served HTML is a shell with no rates in it.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $mellat->id, 'source_type' => 'currency_rates'],
            ['url' => 'https://api.mellatbank.am/api/v1/rate/list', 'is_active' => true]
        );

        // Server-rendered, unlike the rest of this batch. Pinned to /en:
        // ByblosRateParser tells the rate tables from the bank's base-rate
        // table by their English "Buy"/"Sell" headers.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $byblos->id, 'source_type' => 'currency_rates'],
            ['url' => '/en', 'is_active' => true]
        );

        // Fast Bank renders no rates server-side and has no rates page;
        // this endpoint is the only place the figures exist. An empty
        // payType returns cash, non-cash and card in one response.
        //
        // It sits under /api/, which fastbank.am's robots.txt disallows.
        // Scraping it is a deliberate call by the site owner rather than an
        // oversight - see the note in FastBankRateParser.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $fastbank->id, 'source_type' => 'currency_rates'],
            ['url' => 'https://www.fastbank.am/api/exchange-rates?kind=rates&payType=', 'is_active' => true]
        );

        // Branch listings. One endpoint per bank, scraped by BranchScraper
        // (php artisan scrape:branches) rather than seeded - the addresses
        // and opening hours here are the banks' own.
        //
        // Converse returns branches, ATMs and payment terminals through one
        // endpoint, told apart by `type`; ConverseBranchParser keeps only
        // the branches.
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

        // The branch page renders no coordinates; this is the endpoint its
        // own map filter calls, and it carries them plus structured hours.
        OrganizationSource::updateOrCreate(
            ['organization_id' => $aeb->id, 'source_type' => 'branches'],
            [
                'url' => 'https://www.aeb.am/en/branch-service-network/ajax',
                // Without this the endpoint serves a 404 page: it answers
                // only what it considers an AJAX request. Set per source
                // rather than globally - plenty of frameworks switch a page
                // to JSON when they see this header, which would break every
                // parser that reads markup.
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

        // ------------------------------------------------------------------
        // Insurance companies
        //
        // The six CBA-licensed insurers that write compulsory motor TPL
        // (ԱՊՊԱ). Efes and the Export Insurance Agency are licensed too but
        // do not write motor cover, so they are not here.
        //
        // Unlike the banks, quotes are not scraped on a schedule into a
        // table - a motor premium is priced live, per request, from the
        // driver's own plate and ID (see App\Services\Insurance). So these
        // carry no OrganizationSource rows; the slug is the whole contract,
        // matched by InsuranceQuoteProviderFactory and SilMarketQuoteSource.
        //
        // How each is reached (primary -> fallback):
        //   INGO, Armenia, Nairi  own JSON API      -> Sil's Bureau table
        //   Liga, Sil, REGO       Sil's Bureau table (Liga is behind a
        //                         reCAPTCHA and REGO runs no open calculator
        //                         of its own, so the table is their only route)
        $insurers = [
            ['slug' => 'ingo-armenia', 'name' => 'INGO Armenia', 'website' => 'https://ingoarmenia.am'],
            ['slug' => 'armenia-insurance', 'name' => 'Armenia Insurance', 'website' => 'https://armeniainsurance.am'],
            ['slug' => 'nairi-insurance', 'name' => 'Nairi Insurance', 'website' => 'https://nairi-insurance.am'],
            ['slug' => 'liga-insurance', 'name' => 'Liga Insurance', 'website' => 'https://liga.am'],
            ['slug' => 'sil-insurance', 'name' => 'Sil Insurance', 'website' => 'https://silinsurance.am'],
            ['slug' => 'rego-insurance', 'name' => 'REGO Insurance', 'website' => 'https://regoinsurance.am'],
        ];

        foreach ($insurers as $insurer) {
            Organization::firstOrCreate(
                ['slug' => $insurer['slug']],
                [
                    'name' => $insurer['name'],
                    'type' => 'insurance',
                    'website' => $insurer['website'],
                    // Logos are added later - drop a file in
                    // public/images/organizations and set 'logo' here. Until
                    // then the results page shows a branded initials avatar.
                    'logo' => $insurer['logo'] ?? null,
                    'country_code' => 'AM',
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Organizations and sources seeded successfully!');
    }
}
