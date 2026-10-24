<?php

namespace App\Channels;

use App\Contracts\ChannelInterface;
use App\Models\Contact;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;

class EmailChannel implements ChannelInterface
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send an email to a contact
     *
     * @param string $message The email content to send (can be subject + body combined)
     * @param Contact $contact The contact to send the email to
     * @param array $context Additional context for the message
     * @return array Result of the send operation
     */
    public function send(string $message, Contact $contact, array $context = []): array
    {
        // Extract subject and body from context if provided separately
        $subject = $context['subject'] ?? 'Notification';
        $body = $context['body'] ?? $message;
        
        // Check if contact has email consent
        if (!$contact->hasEmailConsent()) {
            Log::warning("Contact does not have email consent", [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
            ]);

            return [
                'success' => false,
                'message' => 'Contact does not have email consent',
                'error_code' => 'NO_EMAIL_CONSENT',
            ];
        }

        // Check if contact has a valid email address
        if (empty($contact->email) || !filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning("Contact has no valid email address", [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email ?? 'NULL',
            ]);

            return [
                'success' => false,
                'message' => 'Contact has no valid email address',
                'error_code' => 'INVALID_EMAIL',
            ];
        }

        try {
            // Send the email using the email service
            $result = $this->emailService->sendEmail(
                $contact->email,
                $subject,
                $body,
                array_merge($context, [
                    'contact_id' => $contact->id,
                    'tenant_id' => $contact->tenant_id,
                ])
            );

            return [
                'success' => true,
                'message_id' => $result['message_id'] ?? null,
                'provider_response' => $result['provider_response'] ?? null,
                'cost' => $result['cost'] ?? 0,
                'currency' => $result['currency'] ?? 'USD',
            ];
        } catch (\Exception $e) {
            Log::error("Failed to send email", [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
                'error_code' => 'SEND_FAILED',
                'error_details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if the email channel is available for sending
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        // Check if email service is configured and available
        try {
            return $this->emailService->isConfigured();
        } catch (\Exception $e) {
            Log::error("Error checking email channel availability", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the channel name/type
     *
     * @return string
     */
    public function getName(): string
    {
        return 'email';
    }
}