<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'writer_id' => Writer::factory(),
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(),
            'language' => 'en',
            'excerpt' => fake()->sentence(20),
            'body' => fake()->paragraphs(5, true),
            'status' => Article::STATUS_DRAFT,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => Article::STATUS_SUBMITTED]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => Article::STATUS_APPROVED,
            'published_at' => now(),
            'reviewed_by' => User::factory()->admin(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => Article::STATUS_REJECTED,
            'rejection_reason' => fake()->sentence(),
            'reviewed_by' => User::factory()->admin(),
        ]);
    }
}
