<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
        $role = 'technician';

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => $role,
            'tech_code' => $this->generateTechCodeForRole($role),
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
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'tech_code' => null,
        ]);
    }

    /**
     * Indicate that the user is a super administrator.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
            'tech_code' => null,
        ]);
    }

    /**
     * Indicate that the user belongs to logistics.
     */
    public function logistics(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'logistics',
            'tech_code' => null,
        ]);
    }

    /**
     * Indicate that the user is a field technician.
     */
    public function technician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'technician',
            'tech_code' => $this->generateTechCodeForRole('technician'),
        ]);
    }

    /**
     * Generate a stock tech code when required by the role.
     */
    protected function generateTechCodeForRole(string $role): ?string
    {
        return $role === 'technician'
            ? strtoupper($this->faker->unique()->bothify('TECH-###??'))
            : null;
    }
}
