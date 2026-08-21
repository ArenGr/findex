<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    /**
     * Seeds only what is real: the feature flags, the 17 licensed banks and
     * their scrape sources, and an admin account.
     *
     * Everything invented lives in its own seeder and is NOT called here.
     * Once demo rows are in the tables nothing distinguishes them from
     * scraped ones - they rendered on the public pages beside real banks,
     * and some of them made claims about real companies:
     *
     *   AdSeeder                  invented offers attributed to real banks
     *                             ("Ameriabank - fixed 11.5% mortgage rate",
     *                             linking to ameriabank.am)
     *   ExchangeOrgSeeder         invented exchange offices, with rates
     *   BranchSeeder              invented addresses and opening hours
     *                             attached to real banks
     *   AutoInsuranceDemoSeeder   invented insurance companies
     *   ArticleSeeder,
     *   ArticleDemoSeeder         seeded editorial filler
     *
     * Run any of them by hand for a populated local environment:
     *   php artisan db:seed --class=ExchangeOrgSeeder
     *
     * Real rates and branches come from the banks themselves:
     *   php artisan scrape:rates
     *   php artisan scrape:branches
     */
    public function run(): void
    {
        // A local login for development. Not shown anywhere public.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(FeatureToggleSeeder::class);
        $this->call(OrganizationSeeder::class);
        $this->call(AdminSeeder::class);
    }
}
