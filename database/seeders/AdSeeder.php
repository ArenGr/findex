<?php

namespace Database\Seeders;

use App\Models\Ad;
use Illuminate\Database\Seeder;

class AdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ads = [
            [
                'placement' => 'organizations_index',
                'side' => 'right',
                'advertiser' => 'Ameriabank',
                'initials' => 'AB',
                'headline' => 'Fixed 11.5% mortgage rate',
                'body' => 'Lock in a fixed rate for 20 years. Pre-approval in 24 hours, no hidden fees.',
                'cta_label' => 'See the offer',
                'href' => 'https://ameriabank.am',
                'sort_order' => 1,
            ],
            [
                'placement' => 'home_rates',
                'side' => 'right',
                'advertiser' => 'Evocabank',
                'initials' => 'EV',
                'headline' => 'Best USD cash rate this week',
                'body' => 'Exchange with zero commission at any Evocabank branch through Sunday.',
                'cta_label' => 'Find a branch',
                'href' => 'https://evocabank.am',
                'sort_order' => 1,
            ],
            [
                'placement' => 'home_hero',
                'side' => 'right',
                'advertiser' => 'Inecobank',
                'initials' => 'IB',
                'headline' => 'Open a savings account in 5 minutes',
                'body' => 'Earn up to 9% annual interest with no minimum balance - apply fully online.',
                'cta_label' => 'Open an account',
                'href' => 'https://inecobank.am',
                'sort_order' => 1,
            ],
        ];

        foreach ($ads as $ad) {
            Ad::firstOrCreate(
                ['placement' => $ad['placement'], 'advertiser' => $ad['advertiser']],
                $ad
            );
        }
    }
}
