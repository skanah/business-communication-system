<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Contact;
use App\Models\ApiKey;
use App\Models\Segment;

class SegmentManagementTest extends TestCase
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
    public function it_can_create_a_segment_with_rules()
    {
        $segmentData = [
            'name' => 'VIP Customers',
            'description' => 'Customers from Nigeria who replied in the last 7 days',
            'rules' => [
                'all' => [
                    ['field' => 'country_code', 'operator' => '=', 'value' => 'NG'],
                    ['field' => 'last_reply_at', 'operator' => '>', 'value' => '-7d'],
                ]
            ],
            'is_active' => true,
            'is_dynamic' => true,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/segments', $segmentData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Segment created successfully',
                     'data' => [
                         'name' => 'VIP Customers',
                         'description' => 'Customers from Nigeria who replied in the last 7 days',
                         'is_active' => true,
                         'is_dynamic' => true,
                     ]
                 ]);

        $this->assertDatabaseHas('segments', [
            'tenant_id' => $this->tenant->id,
            'name' => 'VIP Customers',
            'is_active' => true,
            'is_dynamic' => true,
        ]);
    }

    /** @test */
    public function it_can_preview_segment_matches()
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

        // Create some contacts that match the segment
        $matchingContact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'country_code' => 'NG',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Create a contact that doesn't match
        $nonMatchingContact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'country_code' => 'US',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/segments/{$segment->id}/preview");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'segment' => [
                             'id' => $segment->id,
                             'name' => 'Test Segment',
                         ],
                         'total_matching_count' => 1, // Only one contact matches
                         'preview_contacts' => [
                             [
                                 'id' => $matchingContact->id,
                                 'country_code' => 'NG',
                             ]
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function it_can_test_if_contact_matches_segment()
    {
        // Create a segment
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
        ])->postJson("/api/segments/{$segment->id}/test-contact/{$matchingContact->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'segment_id' => $segment->id,
                         'contact_id' => $matchingContact->id,
                         'matches' => true,
                     ]
                 ]);

        // Test non-matching contact
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson("/api/segments/{$segment->id}/test-contact/{$nonMatchingContact->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'segment_id' => $segment->id,
                         'contact_id' => $nonMatchingContact->id,
                         'matches' => false,
                     ]
                 ]);
    }

    /** @test */
    public function it_can_list_segments()
    {
        // Create some segments
        Segment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson('/api/segments');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'data' => [
                             // Should contain at least 3 segments
                         ]
                     ]
                 ])
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'data',
                         'links' => [
                             'first',
                             'last',
                             'prev',
                             'next'
                         ],
                         'meta' => [
                             'current_page',
                             'from',
                             'last_page',
                             'links',
                             'path',
                             'per_page',
                             'to',
                             'total'
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function it_can_update_a_segment()
    {
        $segment = Segment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
            'description' => 'Old Description',
        ]);

        $updatedData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'rules' => [
                'any' => [
                    ['field' => 'country_code', 'operator' => '=', 'value' => 'NG'],
                    ['field' => 'country_code', 'operator' => '=', 'value' => 'GH'],
                ]
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->putJson("/api/segments/{$segment->id}", $updatedData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Segment updated successfully',
                     'data' => [
                         'name' => 'Updated Name',
                         'description' => 'Updated Description',
                     ]
                 ]);

        $this->assertDatabaseHas('segments', [
            'id' => $segment->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
        ]);
    }

    /** @test */
    public function it_can_delete_a_segment()
    {
        $segment = Segment::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->deleteJson("/api/segments/{$segment->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Segment deleted successfully',
                 ]);

        $this->assertSoftDeleted('segments', [
            'id' => $segment->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_respects_tenant_isolation_for_segments()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id
        ]);
        $otherApiKey = ApiKey::factory()->create([
            'tenant_id' => $otherTenant->id,
            'permissions' => ['segments:read']
        ]);

        // Create a segment in the original tenant
        $segment = Segment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant 1 Segment',
        ]);

        // Try to access the segment from the other tenant - should fail
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherApiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/segments/{$segment->id}");

        // Should return 404 since the segment belongs to a different tenant
        $response->assertStatus(404);
    }

    /** @test */
    public function it_evaluates_complex_segment_rules()
    {
        // Create a segment with complex rules
        $segment = Segment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Complex Segment',
            'rules' => [
                'all' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ],
                'any' => [
                    ['field' => 'country_code', 'operator' => '=', 'value' => 'NG'],
                    ['field' => 'country_code', 'operator' => '=', 'value' => 'GH'],
                ]
            ],
            'is_dynamic' => true,
        ]);

        // Create contacts that match the complex rules
        $matchingContact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'country_code' => 'NG',
        ]);

        // Create a contact that doesn't match (wrong country)
        $nonMatchingContact1 = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'country_code' => 'US',
        ]);

        // Create a contact that doesn't match (inactive)
        $nonMatchingContact2 = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'inactive',
            'country_code' => 'NG',
        ]);

        // Test that the matching contact matches
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson("/api/segments/{$segment->id}/test-contact/{$matchingContact->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'matches' => true,
                     ]
                 ]);

        // Test that the non-matching contacts don't match
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson("/api/segments/{$segment->id}/test-contact/{$nonMatchingContact1->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'matches' => false,
                     ]
                 ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson("/api/segments/{$segment->id}/test-contact/{$nonMatchingContact2->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'matches' => false,
                     ]
                 ]);
    }
}