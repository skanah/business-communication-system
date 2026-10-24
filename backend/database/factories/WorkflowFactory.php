<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workflow>
 */
class WorkflowFactory extends Factory
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
            'graph' => [
                'nodes' => [
                    [
                        'id' => 'trigger-1',
                        'type' => 'trigger',
                        'position' => ['x' => 0, 'y' => 0],
                        'data' => [
                            'trigger_type' => 'contact_created',
                        ],
                    ],
                    [
                        'id' => 'send-sms-1',
                        'type' => 'sendSMS',
                        'position' => ['x' => 200, 'y' => 0],
                        'data' => [
                            'message' => 'Hello {{contact.first_name}}, welcome!',
                        ],
                    ],
                    [
                        'id' => 'end-1',
                        'type' => 'end',
                        'position' => ['x' => 400, 'y' => 0],
                        'data' => [],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'edge-1',
                        'source' => 'trigger-1',
                        'target' => 'send-sms-1',
                    ],
                    [
                        'id' => 'edge-2',
                        'source' => 'send-sms-1',
                        'target' => 'end-1',
                    ],
                ],
            ],
            'node_types' => ['trigger', 'sendSMS', 'end'],
            'is_active' => true,
            'is_draft' => true,
        ];
    }
}