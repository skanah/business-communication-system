<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\EventLog;
use Illuminate\Support\Facades\Log;

class ConsentService
{
    /**
     * Check if a contact has consent for a specific channel
     *
     * @param Contact $contact
     * @param string $channel ('sms' or 'email')
     * @return bool
     */
    public function hasConsent(Contact $contact, string $channel): bool
    {
        switch ($channel) {
            case 'sms':
                return $contact->hasSmsConsent();
            case 'email':
                return $contact->hasEmailConsent();
            default:
                return false;
        }
    }

    /**
     * Grant consent for a specific channel
     *
     * @param Contact $contact
     * @param string $channel ('sms' or 'email')
     * @return bool
     */
    public function grantConsent(Contact $contact, string $channel): bool
    {
        $updates = [];
        
        switch ($channel) {
            case 'sms':
                $updates['sms_consent'] = true;
                $updates['sms_consent_given_at'] = now();
                break;
            case 'email':
                $updates['email_consent'] = true;
                $updates['email_consent_given_at'] = now();
                break;
            default:
                return false;
        }

        $contact->update($updates);

        // Log the consent event
        EventLog::logContactEvent(
            $contact, 
            'consent_granted', 
            [
                'channel' => $channel,
                'previous_status' => $contact->fresh()->getOriginal("{$channel}_consent"),
            ]
        );

        return true;
    }

    /**
     * Revoke consent for a specific channel
     *
     * @param Contact $contact
     * @param string $channel ('sms' or 'email')
     * @return bool
     */
    public function revokeConsent(Contact $contact, string $channel): bool
    {
        $updates = [];
        
        switch ($channel) {
            case 'sms':
                $updates['sms_consent'] = false;
                break;
            case 'email':
                $updates['email_consent'] = false;
                break;
            default:
                return false;
        }

        $contact->update($updates);

        // Log the consent event
        EventLog::logContactEvent(
            $contact, 
            'consent_revoked', 
            [
                'channel' => $channel,
                'previous_status' => $contact->fresh()->getOriginal("{$channel}_consent"),
            ]
        );

        return true;
    }

    /**
     * Verify consent before sending a message
     *
     * @param Contact $contact
     * @param string $channel
     * @param array $context Additional context for consent verification
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public function verifyBeforeSend(Contact $contact, string $channel, array $context = []): array
    {
        // Check if contact is active
        if ($contact->status !== 'active') {
            return [
                'allowed' => false,
                'reason' => 'Contact is not active',
            ];
        }

        // Check specific channel consent
        if (!$this->hasConsent($contact, $channel)) {
            return [
                'allowed' => false,
                'reason' => "Contact has not granted {$channel} consent",
            ];
        }

        // Additional checks could be added here based on context
        // For example, checking if the contact has unsubscribed recently
        // or if there are any compliance rules that apply

        return [
            'allowed' => true,
            'reason' => 'Consent verified',
        ];
    }

    /**
     * Process consent request from contact (e.g., through keyword reply)
     *
     * @param Contact $contact
     * @param string $consentAction ('grant' or 'revoke')
     * @param string $channel ('sms' or 'email')
     * @param string|null $source Source of consent (e.g., 'keyword_reply', 'web_form', 'api_call')
     * @return array ['success' => bool, 'message' => string]
     */
    public function processConsentRequest(Contact $contact, string $consentAction, string $channel, string $source = null): array
    {
        if (!in_array($consentAction, ['grant', 'revoke'])) {
            return [
                'success' => false,
                'message' => 'Invalid consent action. Use "grant" or "revoke".',
            ];
        }

        if (!in_array($channel, ['sms', 'email'])) {
            return [
                'success' => false,
                'message' => 'Invalid channel. Use "sms" or "email".',
            ];
        }

        try {
            if ($consentAction === 'grant') {
                $this->grantConsent($contact, $channel);
                
                return [
                    'success' => true,
                    'message' => ucfirst($channel) . ' consent granted successfully.',
                ];
            } else {
                $this->revokeConsent($contact, $channel);
                
                return [
                    'success' => true,
                    'message' => ucfirst($channel) . ' consent revoked successfully.',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to process consent request', [
                'contact_id' => $contact->id,
                'consent_action' => $consentAction,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process consent request: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get consent summary for a contact
     *
     * @param Contact $contact
     * @return array
     */
    public function getConsentSummary(Contact $contact): array
    {
        return [
            'sms' => [
                'consent' => $contact->sms_consent,
                'given_at' => $contact->sms_consent_given_at,
                'has_consent' => $contact->hasSmsConsent(),
            ],
            'email' => [
                'consent' => $contact->email_consent,
                'given_at' => $contact->email_consent_given_at,
                'has_consent' => $contact->hasEmailConsent(),
            ],
            'contact_status' => $contact->status,
        ];
    }
}