<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * role/organization_id are deliberately absent from User::$fillable
     * (see User::canAccessPanel's docblock), so a plain state() array
     * here would be silently dropped - afterMaking()+forceFill() instead,
     * matching how RegisteredOrganizationController/AdminSeeder/CreateAdmin
     * set them outside mass assignment.
     */
    public function organization(?Organization $organization = null): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill([
            'role' => UserRole::ORGANIZATION,
            'organization_id' => $organization?->id ?? Organization::factory()->create()->id,
        ]));
    }

    public function admin(): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill([
            'role' => UserRole::ADMIN,
        ]));
    }
}
