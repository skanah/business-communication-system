<?php

namespace App\Http\Middleware;

use App\Services\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ConsentEnforcementMiddleware
{
    protected $consentService;

    public function __construct(ConsentService $consentService)
    {
        $this->consentService = $consentService;
    }

    /**
     * Handle an incoming request to enforce consent rules.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $channel
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $channel = null)
    {
        // Determine the channel from the request if not provided
        if (!$channel) {
            // Try to determine from request content or route
            $contentType = $request->header('Content-Type');
            if (str_contains($contentType, 'email')) {
                $channel = 'email';
            } elseif (str_contains($contentType, 'sms') || str_contains(strtolower($request->path()), 'sms')) {
                $channel = 'sms';
            } else {
                // Default to checking both
                $channel = 'both';
            }
        }

        // Get contact ID from request
        $contactId = $request->route('contact') ?? $request->route('id') ?? $request->input('contact_id');
        
        if ($contactId) {
            // Load the contact
            $contact = \App\Models\Contact::where('tenant_id', $request->user()->tenant->id)->find($contactId);
            
            if ($contact) {
                if ($channel === 'both') {
                    // Check both SMS and email consent
                    $smsConsent = $this->consentService->verifyBeforeSend($contact, 'sms');
                    $emailConsent = $this->consentService->verifyBeforeSend($contact, 'email');
                    
                    if (!$smsConsent['allowed'] && !$emailConsent['allowed']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Contact has not granted consent for messaging',
                            'details' => [
                                'sms' => $smsConsent,
                                'email' => $emailConsent,
                            ]
                        ], 403);
                    }
                } else {
                    // Check specific channel consent
                    $consentCheck = $this->consentService->verifyBeforeSend($contact, $channel);
                    
                    if (!$consentCheck['allowed']) {
                        return response()->json([
                            'success' => false,
                            'message' => $consentCheck['reason'],
                            'details' => $consentCheck,
                        ], 403);
                    }
                }
            }
        }

        return $next($request);
    }
}