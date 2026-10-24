<?php

namespace App\Channels;

use App\Contracts\ChannelInterface;
use App\Models\Contact;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class SMSChannel implements ChannelInterface
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send an SMS message to a contact
     *
     * @param string $message The message content to send
     * @param Contact $contact The contact to send the message to
     * @param array $context Additional context for the message
     * @return array Result of the send operation
     */
    public function send(string $message, Contact $contact, array $context = []): array
    {
        // Check if contact has SMS consent
        if (!$contact->hasSmsConsent()) {
            Log::warning("Contact does not have SMS consent", [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
                'contact_phone' => $contact->phone,
            ]);

            return [
                'success' => false,
                'message' => 'Contact does not have SMS consent',
                'error_code' => 'NO_SMS_CONSENT',
            ];
        }

        // Check if contact has a valid phone number
        if (empty($contact->phone)) {
            Log::warning("Contact has no phone number", [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
            ]);

            return [
                'success' => false,
                'message' => 'Contact has no phone number',
                'error_code' => 'NO_PHONE_NUMBER',
            ];
        }

        try {
            // Send the SMS using the SMS service
            $result = $this->smsService->sendSms(
                $contact->phone,
                $message,
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
            Log::error("Failed to send SMS", [
                'contact_id' => $contact->id,
                'contact_phone' => $contact->phone,
                'message' => substr($message, 0, 100) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage(),
                'error_code' => 'SEND_FAILED',
                'error_details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if the SMS channel is available for sending
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        // Check if SMS service is configured and available
        try {
            return $this->smsService->isConfigured();
        } catch (\Exception $e) {
            Log::error("Error checking SMS channel availability", [
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
        return 'sms';
    }
}