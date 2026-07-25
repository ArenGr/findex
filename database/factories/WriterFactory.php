<?php

namespace Database\Factories;

use App\Models\Writer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Writer>
 */
class WriterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->unique()->slug(),
            'expertise' => fake()->sentence(),
            'topics' => implode(', ', fake()->words(3)),
            'is_active' => true,
        ];
    }
}
