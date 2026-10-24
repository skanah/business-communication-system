<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'key' => 'sk-' . bin2hex(random_bytes(32)),
            'tenant_id' => \App\Models\Tenant::factory(),
            'permissions' => ['*'],
            'expires_at' => null,
        ];
    }
}