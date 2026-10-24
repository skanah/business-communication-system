<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Contact;
use App\Models\ApiKey;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;
    protected $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable foreign key constraints for SQLite in tests
        DB::statement('PRAGMA foreign_keys=OFF;');

        // Create a tenant
        $this->tenant = Tenant::factory()->create();

        // Create a user associated with the tenant
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        // Create an API key for the tenant
        $this->apiKey = ApiKey::factory()->create([
            'tenant_id' => $this->tenant->id,
            'permissions' => ['contacts:read', 'contacts:write']
        ]);
    }

    protected function tearDown(): void
    {
        // Re-enable foreign key constraints after test
        DB::statement('PRAGMA foreign_keys=ON;');

        parent::tearDown();
    }

    /** @test */
    public function it_can_create_a_contact()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'sms_consent' => true,
            'email_consent' => true,
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Contact created successfully',
                 ]);

        $this->assertDatabaseHas('contacts', [
            'tenant_id' => $this->tenant->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'sms_consent' => true,
            'email_consent' => true,
        ]);
    }

    /** @test */
    public function it_can_retrieve_a_contact()
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/contacts/{$contact->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $contact->id,
                         'first_name' => 'Jane',
                         'last_name' => 'Smith',
                         'email' => 'jane.smith@example.com',
                     ]
                 ]);
    }

    /** @test */
    public function it_can_update_a_contact()
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Original',
            'last_name' => 'Name',
            'email' => 'original@example.com',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->putJson("/api/contacts/{$contact->id}", [
            'first_name' => 'Updated',
            'email' => 'updated@example.com',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Contact updated successfully',
                 ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'first_name' => 'Updated',
            'email' => 'updated@example.com',
        ]);
    }

    /** @test */
    public function it_can_delete_a_contact()
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->deleteJson("/api/contacts/{$contact->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Contact deleted successfully',
                 ]);

        $this->assertSoftDeleted('contacts', [
            'id' => $contact->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_list_contacts()
    {
        Contact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->getJson('/api/contacts');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'data' => [
                             // Should contain at least 3 contacts
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
    public function it_respects_tenant_isolation()
    {
        // Create a contact in the current tenant
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id
        ]);
        $otherApiKey = ApiKey::factory()->create([
            'tenant_id' => $otherTenant->id,
            'permissions' => ['contacts:read']
        ]);

        // Try to access the contact from the other tenant
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherApiKey->key,
            'Accept' => 'application/json',
        ])->getJson("/api/contacts/{$contact->id}");

        // Should return 404 since the contact belongs to a different tenant
        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_gdpr_anonymization()
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Sensitive',
            'last_name' => 'Data',
            'email' => 'sensitive@example.com',
            'phone' => '+1234567890',
            'sms_consent' => true,
            'email_consent' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson("/api/contacts/{$contact->id}/anonymize");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Contact anonymized successfully for GDPR compliance',
                 ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'first_name' => 'Anonymized',
            'last_name' => 'Contact',
            'email' => null,
            'phone' => null,
            'sms_consent' => false,
            'email_consent' => false,
            'status' => 'inactive',
        ]);
    }

    /** @test */
    public function it_validates_contact_data()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey->key,
            'Accept' => 'application/json',
        ])->postJson('/api/contacts', [
            'email' => 'invalid-email', // Invalid email format
            'phone' => str_repeat('1', 30), // Too long phone number
        ]);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Validation failed',
                 ]);
    }
}