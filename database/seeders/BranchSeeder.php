<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Demo branch locations (with real Yerevan-district and regional-city
 * coordinates) for the banks (OrganizationSeeder) and exchange offices
 * (ExchangeOrgSeeder) - without this, RateController's city filter and the
 * /rates "find nearby" distance sort have nothing to show (both are
 * entirely driven by the Branch table, which nothing else populates).
 *
 * Called from DatabaseSeeder::run() after both of those, so every
 * organization slug referenced here already exists.
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            'acba' => [
                ['name' => 'ACBA Kentron', 'city' => 'Yerevan', 'address' => 'Amiryan St 2', 'lat' => 40.1811, 'lng' => 44.5136],
                ['name' => 'ACBA Arabkir', 'city' => 'Yerevan', 'address' => 'Komitas Ave 15', 'lat' => 40.1950, 'lng' => 44.4850],
                ['name' => 'ACBA Gyumri', 'city' => 'Gyumri', 'address' => 'Vardanants St 4', 'lat' => 40.7942, 'lng' => 43.8461],
            ],
            'ineco' => [
                ['name' => 'Inecobank Kentron', 'city' => 'Yerevan', 'address' => 'Vazgen Sargsyan St 6/1', 'lat' => 40.1795, 'lng' => 44.5115],
                ['name' => 'Inecobank Davtashen', 'city' => 'Yerevan', 'address' => 'Davtashen 4th District', 'lat' => 40.2150, 'lng' => 44.4650],
            ],
            'ameria' => [
                ['name' => 'Ameriabank Kentron', 'city' => 'Yerevan', 'address' => 'Vazgen Sargsyan St 2', 'lat' => 40.1801, 'lng' => 44.5127],
                ['name' => 'Ameriabank Nor Nork', 'city' => 'Yerevan', 'address' => ' Isakov Ave 28', 'lat' => 40.2050, 'lng' => 44.5550],
            ],
            'unibank' => [
                ['name' => 'Unibank Kentron', 'city' => 'Yerevan', 'address' => 'Charents St 12', 'lat' => 40.1840, 'lng' => 44.5240],
                ['name' => 'Unibank Vanadzor', 'city' => 'Vanadzor', 'address' => 'Tigran Mets St 3', 'lat' => 40.8128, 'lng' => 44.4885],
            ],
            'evoca' => [
                ['name' => 'Evocabank Kentron', 'city' => 'Yerevan', 'address' => 'Nalbandyan St 26', 'lat' => 40.1825, 'lng' => 44.5150],
                ['name' => 'Evocabank Malatia', 'city' => 'Yerevan', 'address' => 'Gyumri Highway 12', 'lat' => 40.1550, 'lng' => 44.4550],
            ],
            'araratbank' => [
                ['name' => 'Ararat Bank Kentron', 'city' => 'Yerevan', 'address' => 'Khorenatsi St 15', 'lat' => 40.1770, 'lng' => 44.5100],
                ['name' => 'Ararat Bank Vagharshapat', 'city' => 'Vagharshapat', 'address' => 'Mesrop Mashtots St 1', 'lat' => 40.1611, 'lng' => 44.2916],
            ],
            'aeb' => [
                ['name' => 'AEB Kentron', 'city' => 'Yerevan', 'address' => 'Mashtots Ave 29', 'lat' => 40.1780, 'lng' => 44.5090],
            ],
            'vtb' => [
                ['name' => 'VTB Armenia Kentron', 'city' => 'Yerevan', 'address' => 'Grigor Lusavorich St 6', 'lat' => 40.1850, 'lng' => 44.5160],
                ['name' => 'VTB Armenia Achapnyak', 'city' => 'Yerevan', 'address' => 'Baghramyan Ave 71', 'lat' => 40.1950, 'lng' => 44.4550],
            ],
            'idbank' => [
                ['name' => 'ID Bank Kentron', 'city' => 'Yerevan', 'address' => 'Tumanyan St 21', 'lat' => 40.1830, 'lng' => 44.5180],
                ['name' => 'ID Bank Kanaker-Zeytun', 'city' => 'Yerevan', 'address' => 'Manandyan St 8', 'lat' => 40.2150, 'lng' => 44.5350],
            ],
            'artsakhbank' => [
                ['name' => 'Artsakhbank Kentron', 'city' => 'Yerevan', 'address' => 'Sayat-Nova Ave 10', 'lat' => 40.1815, 'lng' => 44.5175],
            ],
        ];

        $exchangeOffices = [
            'demo-yerevan-city-exchange' => [
                ['name' => 'Yerevan City Exchange - Kentron', 'city' => 'Yerevan', 'address' => 'Abovyan St 3', 'lat' => 40.1808, 'lng' => 44.5130],
            ],
            'demo-golden-rate-exchange' => [
                ['name' => 'Golden Rate Exchange - Kentron', 'city' => 'Yerevan', 'address' => 'Sayat-Nova Ave 22', 'lat' => 40.1795, 'lng' => 44.5195],
            ],
            'demo-capital-currency-house' => [
                ['name' => 'Capital Currency House - Dalma Mall', 'city' => 'Yerevan', 'address' => 'Dalma Garden Mall, Erebuni', 'lat' => 40.1350, 'lng' => 44.4950],
                ['name' => 'Capital Currency House - Rossia Mall', 'city' => 'Yerevan', 'address' => 'Rossia Mall, Erebuni', 'lat' => 40.1450, 'lng' => 44.4850],
            ],
            'demo-swift-exchange-point' => [
                ['name' => 'Swift Exchange Point - Kentron', 'city' => 'Yerevan', 'address' => 'Mashtots Ave 40', 'lat' => 40.1785, 'lng' => 44.5080],
            ],
            'demo-northside-exchange' => [
                ['name' => 'Northside Exchange - Avan', 'city' => 'Yerevan', 'address' => 'Nairi Zaryan St 5', 'lat' => 40.2250, 'lng' => 44.5450],
            ],
        ];

        // Demo opening hours, in Yerevan local time. Two realistic patterns:
        // banks keep office hours and shut at the weekend, exchange offices
        // open longer and trade seven days - which is exactly the difference
        // "Open now" exists to surface at 7pm on a Sunday.
        $bankHours = [
            'mon' => ['09:30', '17:30'], 'tue' => ['09:30', '17:30'], 'wed' => ['09:30', '17:30'],
            'thu' => ['09:30', '17:30'], 'fri' => ['09:30', '17:30'], 'sat' => null, 'sun' => null,
        ];

        $exchangeHours = [
            'mon' => ['09:00', '21:00'], 'tue' => ['09:00', '21:00'], 'wed' => ['09:00', '21:00'],
            'thu' => ['09:00', '21:00'], 'fri' => ['09:00', '21:00'],
            'sat' => ['10:00', '20:00'], 'sun' => ['10:00', '18:00'],
        ];

        // One branch of each kind is left without hours on purpose, so the
        // "we do not know" path stays exercised in demo data rather than only
        // in tests - it renders differently from "closed" and should.
        $withoutHours = ['ACBA Gyumri', 'Northside Exchange - Avan'];

        foreach ([...$banks, ...$exchangeOffices] as $slug => $branches) {
            $organization = Organization::where('slug', $slug)->first();

            if (! $organization) {
                continue;
            }

            foreach ($branches as $branch) {
                Branch::updateOrCreate(
                    ['organization_id' => $organization->id, 'name' => $branch['name']],
                    [
                        'city' => $branch['city'],
                        'address' => $branch['address'],
                        'latitude' => $branch['lat'],
                        'longitude' => $branch['lng'],
                        'is_active' => true,
                        'opening_hours' => in_array($branch['name'], $withoutHours, true)
                            ? null
                            : ($organization->type === 'exchange' ? $exchangeHours : $bankHours),
                    ]
                );
            }
        }

        $this->command?->info('Demo branches seeded for banks and exchange offices.');
    }
}
