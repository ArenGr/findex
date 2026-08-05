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
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(OrganizationSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(ArticleSeeder::class);
        $this->call(ArticleDemoSeeder::class);
        $this->call(AdSeeder::class);
        $this->call(AutoInsuranceDemoSeeder::class);
        $this->call(ExchangeOrgSeeder::class);
        // After both of the above - it seeds branches onto organizations
        // created by each.
        $this->call(BranchSeeder::class);
    }
}
