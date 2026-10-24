<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Segment>
 */
class SegmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'rules' => [
                'all' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ]
            ],
            'contact_count' => 0,
            'is_active' => true,
            'is_dynamic' => true,
        ];
    }
}