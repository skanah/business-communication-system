<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Contact;
use App\Models\ApiKey;
use App\Models\Segment;
use App\Models\Workflow;
use App\Models\Campaign;

class CampaignManagementTest extends TestCase
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

        // Create an API key for the tenant with campaign permissions
        $this->apiKey = ApiKey::factory()->create([
            'tenant_id' => $this->tenant->id,
            'permissions' => ['*']  // Allow all permissions for testing
        ]);
    }

    /** @test */
    public function it_can_create_a_broadcast_campaign()
    {
        // Create a segment first
        $segment = Segment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nigerian Customers',
            'rules' => [
                'all' => [
                    ['field' => 'country_code', 'operator' => '=', 'value' => 'NG'],
                ]
            ],
            'is_dynamic' => true,
        ]);

        // Create a workflow
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Welcome Workflow',
        ]);

        $campaignData = [
            'name' => 'Welcome Campaign',
            'description' => 'Welcome campaign for new Nigerian customers',
            'type' => 'broadcast',
            'segment_id' => $segment->id,
            'workflow_id' => $workflow->id,
            'is_active' => true,
            'settings' => [
                'batch_size' => 50,
                'delay_between_batches' => 1,
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/campaigns', $campaignData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Campaign created successfully',
                     'data' => [
                         'name' => 'Welcome Campaign',
                         'type' => 'broadcast',
                         'is_active' => true,
                     ]
                 ]);

        $this->assertDatabaseHas('campaigns', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Welcome Campaign',
            'type' => 'broadcast',
            'segment_id' => $segment->id,
            'workflow_id' => $workflow->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_create_a_drip_campaign()
    {
        // Create a segment
        $segment = Segment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Customers',
            'rules' => [
                'all' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ]
            ],
            'is_dynamic' => true,
        ]);

        // Create a workflow
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Drip Workflow',
        ]);

        $campaignData = [
            'name' => 'Drip Campaign',
            'description' => 'Drip campaign for active customers',
            'type' => 'drip',
            'segment_id' => $segment->id,
            'workflow_id' => $workflow->id,
            'is_active' => true,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/campaigns', $campaignData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Campaign created successfully',
                     'data' => [
                         'name' => 'Drip Campaign',
                         'type' => 'drip',
                     ]
                 ]);

        $this->assertDatabaseHas('campaigns', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Drip Campaign',
            'type' => 'drip',
        ]);
    }

    /** @test */
    public function it_can_create_a_triggered_campaign()
    {
        // Create a workflow for the triggered campaign
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Triggered Workflow',
        ]);

        $campaignData = [
            'name' => 'Abandoned Cart Campaign',
            'description' => 'Triggered when customer abandons cart',
            'type' => 'triggered',
            'workflow_id' => $workflow->id,
            'is_active' => true,
            'settings' => [
                'trigger_type' => 'cart_abandoned',
                'trigger_conditions' => [
                    'all' => [
                        ['field' => 'cart_value', 'operator' => '>', 'value' => 50],
                    ]
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/campaigns', $campaignData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Campaign created successfully',
                     'data' => [
                         'name' => 'Abandoned Cart Campaign',
                         'type' => 'triggered',
                     ]
                 ]);

        $this->assertDatabaseHas('campaigns', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Abandoned Cart Campaign',
            'type' => 'triggered',
            'workflow_id' => $workflow->id,
        ]);
    }

    /** @test */
    public function it_can_list_campaigns()
    {
        // Create some campaigns
        Campaign::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson('/api/campaigns');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'data' => [
                             // Should contain at least 3 campaigns
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function it_can_retrieve_a_campaign()
    {
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Campaign',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/campaigns/{$campaign->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $campaign->id,
                         'name' => 'Test Campaign',
                     ]
                 ]);
    }

    /** @test */
    public function it_can_update_a_campaign()
    {
        $campaign = Campaign::factory()->create([
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
        ])->putJson("/api/campaigns/{$campaign->id}", $updatedData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Campaign updated successfully',
                     'data' => [
                         'id' => $campaign->id,
                         'name' => 'Updated Name',
                         'description' => 'Updated Description',
                     ]
                 ]);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
        ]);
    }

    /** @test */
    public function it_can_delete_a_campaign()
    {
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->deleteJson("/api/campaigns/{$campaign->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Campaign deleted successfully',
                 ]);

        $this->assertSoftDeleted('campaigns', [
            'id' => $campaign->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_preview_campaign_matches()
    {
        // Create a segment
        $segment = Segment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Segment',
            'rules' => [
                'all' => [
                    ['field' => 'country_code', 'operator' => '=', 'value' => 'NG'],
                ]
            ],
            'is_dynamic' => true,
        ]);

        // Create a workflow
        $workflow = Workflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Workflow',
        ]);

        // Create a campaign with the segment
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Preview Test Campaign',
            'type' => 'broadcast',
            'segment_id' => $segment->id,
            'workflow_id' => $workflow->id,
        ]);

        // Create matching contacts
        Contact::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'country_code' => 'NG',
        ]);

        // Create non-matching contacts
        Contact::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'country_code' => 'US',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/campaigns/{$campaign->id}/preview");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'campaign' => [
                             'id' => $campaign->id,
                             'name' => 'Preview Test Campaign',
                         ],
                         'total_matching_count' => 5, // Only NG contacts should match
                     ]
                 ]);
    }

    /** @test */
    public function it_respects_tenant_isolation_for_campaigns()
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

        // Create a campaign in the original tenant
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant 1 Campaign',
        ]);

        // Try to access the campaign from the other tenant - should return 404
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherApiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/campaigns/{$campaign->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_validates_campaign_data()
    {
        $invalidCampaignData = [
            'name' => '', // Required field
            'type' => 'invalid_type', // Invalid type
            'segment_id' => 999999, // Non-existent segment
            'workflow_id' => 999999, // Non-existent workflow
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/campaigns', $invalidCampaignData);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Validation failed',
                 ]);
    }

    /** @test */
    public function it_can_get_campaign_statistics()
    {
        // Create a campaign
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stats Test Campaign',
            'type' => 'broadcast',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/campaigns/{$campaign->id}/stats");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'campaign' => [
                             'id' => $campaign->id,
                             'name' => 'Stats Test Campaign',
                         ],
                         'statistics' => [
                             'total_contacts' => 0,
                             'sent' => 0,
                             'delivered' => 0,
                             'opened' => 0,
                             'clicked' => 0,
                             'replied' => 0,
                             'bounced' => 0,
                             'unsubscribed' => 0,
                         ]
                     ]
                 ]);
    }
}