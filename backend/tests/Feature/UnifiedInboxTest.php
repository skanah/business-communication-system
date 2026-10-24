<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SmsMessage;
use App\Models\EmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnifiedInboxTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create a Sanctum personal access token for authenticated requests
        $this->token = $this->user->createToken('test')->plainTextToken;

        // Create some test messages
        SmsMessage::create([
            'user_id' => $this->user->id,
            'sender_number' => '+1234567890',
            'receiver_number' => '+0987654321',
            'message_content' => 'Test SMS message',
            'message_type' => 'incoming',
            'message_status' => 'received',
            'provider' => 'test',
            'provider_message_id' => 'SMS-001',
            'cost' => 100,
            'currency' => 'USD'
        ]);

        EmailMessage::create([
            'user_id' => $this->user->id,
            'sender_email' => 'sender@example.com',
            'receiver_email' => 'test@example.com',
            'subject' => 'Test Email Subject',
            'message_content' => 'Test email content',
            'message_type' => 'incoming',
            'message_status' => 'received',
            'provider' => 'smtp',
            'provider_message_id' => 'EMAIL-001',
            'cost' => 100,
            'currency' => 'USD',
            'size_bytes' => 1024
        ]);
    }

    public function test_get_unified_messages()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->getJson('/api/inbox/messages');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'messages' => [
                        '*' => [
                            'id',
                            'type',
                            'channel',
                            'message_type',
                            'message_status',
                            'sender',
                            'receiver',
                            'subject',
                            'content',
                            'cost',
                            'currency',
                            'created_at'
                        ]
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page'
                    ]
                ]
            ]);

        // Should have 2 messages (1 SMS + 1 Email)
        $this->assertCount(2, $response->json('data.messages'));
    }

    public function test_filter_messages_by_channel()
    {
        // Filter by SMS only
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->getJson('/api/inbox/messages?channel=sms');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.messages'));
        $this->assertEquals('SMS', $response->json('data.messages.0.channel'));
    }

    public function test_filter_messages_by_type()
    {
        // Filter by incoming messages only
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->getJson('/api/inbox/messages?message_type=incoming');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.messages'));
        foreach ($response->json('data.messages') as $message) {
            $this->assertEquals('incoming', $message['message_type']);
        }
    }

    public function test_search_messages()
    {
        // Search for "SMS" content
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->getJson('/api/inbox/messages?search=SMS');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.messages'));
        $this->assertStringContainsString('SMS', $response->json('data.messages.0.content'));
    }

    public function test_get_message_by_id()
    {
        $smsMessage = SmsMessage::where('user_id', $this->user->id)->first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->getJson("/api/inbox/messages/{$smsMessage->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'channel',
                    'sender',
                    'receiver',
                    'content'
                ]
            ]);
    }

    public function test_get_unified_inbox_statistics()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->getJson('/api/inbox/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_messages',
                    'sms_messages' => [
                        'total',
                        'incoming',
                        'outgoing',
                        'total_cost'
                    ],
                    'email_messages' => [
                        'total',
                        'incoming',
                        'outgoing',
                        'total_cost',
                        'with_attachments'
                    ],
                    'unread_messages',
                    'failed_messages',
                    'total_cost',
                    'messages_today'
                ]
            ]);

        $this->assertEquals(2, $response->json('data.total_messages'));
        $this->assertEquals(1, $response->json('data.sms_messages.total'));
        $this->assertEquals(1, $response->json('data.email_messages.total'));
    }

    public function test_mark_message_as_read()
    {
        $smsMessage = SmsMessage::where('user_id', $this->user->id)
            ->where('message_status', 'received')
            ->first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->putJson("/api/inbox/messages/{$smsMessage->id}/read");

        $response->assertStatus(200);

        // Verify the message was marked as read
        $this->assertDatabaseHas('sms_messages', [
            'id' => $smsMessage->id,
            'message_status' => 'read'
        ]);
    }

    public function test_delete_message()
    {
        $smsMessage = SmsMessage::where('user_id', $this->user->id)->first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->deleteJson("/api/inbox/messages/{$smsMessage->id}");

        $response->assertStatus(200);

        // Verify the message was deleted
        $this->assertDatabaseMissing('sms_messages', [
            'id' => $smsMessage->id
        ]);
    }

    public function test_unauthorized_access()
    {
        // Test without authentication
        $response = $this->getJson('/api/inbox/messages');

        $response->assertStatus(401);
    }

    public function test_access_other_users_messages()
    {
        // Create another user
        $otherUser = User::factory()->create();

        // Create a message for the other user
        SmsMessage::create([
            'user_id' => $otherUser->id,
            'sender_number' => '+1111111111',
            'receiver_number' => '+2222222222',
            'message_content' => 'Other user message',
            'message_type' => 'incoming',
            'message_status' => 'received',
            'provider' => 'test',
            'provider_message_id' => 'SMS-002',
            'cost' => 100,
            'currency' => 'USD'
        ]);

        // Try to access the other user's message
        $otherMessage = SmsMessage::where('user_id', $otherUser->id)->first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->getJson("/api/inbox/messages/{$otherMessage->id}");

        $response->assertStatus(404);
    }
}
