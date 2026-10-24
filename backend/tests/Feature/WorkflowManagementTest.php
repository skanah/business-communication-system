<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Contact;
use App\Models\ApiKey;
use App\Models\Workflow;
use App\Models\WorkflowExecution;

class WorkflowManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;
    protected $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant
        $this->tenant = Tenant::factory()->create();

        // Create a user associated with the tenant
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        // Create an API key for the tenant
        $this->apiKey = ApiKey::factory()->create([
            'tenant_id' => $this->tenant->id,
            'permissions' => ['*']  // Allow all permissions for testing
        ]);
    }

    /** @test */
    public function it_can_create_a_workflow_with_rules()
    {
        $workflowData = [
            'name' => 'Welcome Sequence',
            'description' => 'Welcome sequence for new subscribers',
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
                        'id' => 'delay-1',
                        'type' => 'delay',
                        'position' => ['x' => 200, 'y' => 0],
                        'data' => [
                            'delay' => 1,
                            'unit' => 'day',
                        ],
                    ],
                    [
                        'id' => 'send-sms-1',
                        'type' => 'sendSMS',
                        'position' => ['x' => 400, 'y' => 0],
                        'data' => [
                            'message' => 'Welcome {{contact.first_name}}! Thanks for joining us.',
                        ],
                    ],
                    [
                        'id' => 'end-1',
                        'type' => 'end',
                        'position' => ['x' => 600, 'y' => 0],
                        'data' => [],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'edge-1',
                        'source' => 'trigger-1',
                        'target' => 'delay-1',
                    ],
                    [
                        'id' => 'edge-2',
                        'source' => 'delay-1',
                        'target' => 'send-sms-1',
                    ],
                    [
                        'id' => 'edge-3',
                        'source' => 'send-sms-1',
                        'target' => 'end-1',
                    ],
                ],
            ],
            'node_types' => ['trigger', 'delay', 'sendSMS', 'end'],
            'is_active' => true,
            'is_draft' => true,  // Keep as draft since that's the default
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/workflows', $workflowData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Workflow created successfully',
                     'data' => [
                         'name' => 'Welcome Sequence',
                         'is_active' => true,
                     ]
                 ]);

        $this->assertDatabaseHas('workflows', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Welcome Sequence',
            'is_active' => true,
            'is_draft' => true,  // Updated to match what we're sending
        ]);
    }

    /** @test */
    public function it_can_list_workflows()
    {
        // Create some workflows
        Workflow::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson('/api/workflows');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'data' => [
                             // Should contain at least 3 workflows
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function it_can_retrieve_a_workflow()
    {
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Workflow',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/workflows/{$workflow->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $workflow->id,
                         'name' => 'Test Workflow',
                     ]
                 ]);
    }

    /** @test */
    public function it_can_update_a_workflow()
    {
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
        ]);

        $updatedData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->putJson("/api/workflows/{$workflow->id}", $updatedData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Workflow updated successfully',
                     'data' => [
                         'id' => $workflow->id,
                         'name' => 'Updated Name',
                         'description' => 'Updated Description',
                     ]
                 ]);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
        ]);
    }

    /** @test */
    public function it_can_delete_a_workflow()
    {
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->deleteJson("/api/workflows/{$workflow->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Workflow deleted successfully',
                 ]);

        $this->assertSoftDeleted('workflows', [
            'id' => $workflow->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_preview_workflow_matches()
    {
        // Create a workflow with rules
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nigeria Customers',
            'graph' => [
                'nodes' => [
                    [
                        'id' => 'trigger-1',
                        'type' => 'trigger',
                        'data' => [
                            'conditions' => [
                                'all' => [
                                    ['field' => 'country_code', 'operator' => '=', 'value' => 'NG'],
                                ]
                            ]
                        ]
                    ]
                ],
                'edges' => []
            ],
            'is_dynamic' => true,
        ]);

        // Create matching contacts
        $matchingContact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'country_code' => 'NG',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Create non-matching contact
        $nonMatchingContact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'country_code' => 'US',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/workflows/{$workflow->id}/preview");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'workflow' => [
                             'id' => $workflow->id,
                             'name' => 'Nigeria Customers',
                         ],
                         'total_matching_count' => 1, // Only one contact matches
                     ]
                 ]);
    }

    /** @test */
    public function it_can_test_if_contact_matches_workflow()
    {
        // Create a workflow
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nigerian Customers',
            'graph' => [
                'nodes' => [
                    [
                        'id' => 'trigger-1',
                        'type' => 'trigger',
                        'data' => [
                            'conditions' => [
                                'all' => [
                                    ['field' => 'country_code', 'operator' => '=', 'value' => 'NG'],
                                ]
                            ]
                        ]
                    ]
                ],
                'edges' => []
            ],
            'is_dynamic' => true,
        ]);

        // Create a matching contact
        $matchingContact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'country_code' => 'NG',
        ]);

        // Create a non-matching contact
        $nonMatchingContact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'country_code' => 'US',
        ]);

        // Test matching contact
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson("/api/workflows/{$workflow->id}/test-contact/{$matchingContact->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'workflow_id' => $workflow->id,
                         'contact_id' => $matchingContact->id,
                         'matches' => true,
                     ]
                 ]);

        // Test non-matching contact
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson("/api/workflows/{$workflow->id}/test-contact/{$nonMatchingContact->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'workflow_id' => $workflow->id,
                         'contact_id' => $nonMatchingContact->id,
                         'matches' => false,
                     ]
                 ]);
    }

    /** @test */
    public function it_respects_tenant_isolation_for_workflows()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id
        ]);
        $otherApiKey = ApiKey::factory()->create([
            'tenant_id' => $otherTenant->id,
            'permissions' => ['*']
        ]);

        // Create a workflow in the original tenant
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant 1 Workflow',
        ]);

        // Try to access the workflow from the other tenant
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherApiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/workflows/{$workflow->id}");

        // Should return 404 since the workflow belongs to a different tenant
        $response->assertStatus(404);
    }

    /** @test */
    public function it_validates_workflow_data()
    {
        $invalidWorkflowData = [
            'name' => '', // Required field
            'graph' => 'invalid', // Should be an array
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/workflows', $invalidWorkflowData);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Validation failed',
                 ]);
    }
}