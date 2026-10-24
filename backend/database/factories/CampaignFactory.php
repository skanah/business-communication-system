<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\Segment;
use App\Models\Workflow;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'type' => $this->faker->randomElement(['broadcast', 'drip', 'triggered']),
            'segment_id' => Segment::factory(),
            'workflow_id' => Workflow::factory(),
            'status' => $this->faker->randomElement(['draft', 'scheduled', 'running', 'completed', 'paused', 'cancelled']),
            'scheduled_at' => $this->faker->optional()->dateTime,
            'started_at' => $this->faker->optional()->dateTime,
            'completed_at' => $this->faker->optional()->dateTime,
            'settings' => [
                'batch_size' => $this->faker->numberBetween(10, 100),
                'delay_between_batches' => $this->faker->numberBetween(0, 5),
            ],
            'total_contacts' => 0,
            'sent_count' => 0,
            'delivered_count' => 0,
            'opened_count' => 0,
            'clicked_count' => 0,
            'replied_count' => 0,
            'bounced_count' => 0,
            'unsubscribed_count' => 0,
            'is_active' => true,
            'is_dynamic' => true,
        ];
    }
}