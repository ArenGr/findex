<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'type' => fake()->randomElement(Organization::TYPES),
            'website' => fake()->url(),
            'country_code' => 'AM',
            'is_active' => true,
        ];
    }
}
