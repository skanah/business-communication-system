<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\CommunicationProfile;
use App\Models\CallLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebRtcController extends BaseController
{
    /**
     * Get WebRTC configuration for authenticated user
     */
    public function getWebRtcConfiguration(Request $request)
    {
        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            $profile = CommunicationProfile::create([
                'user_id' => $user->id,
                'webrtc_id' => 'webrtc_' . $user->id . '_' . Str::random(6),
                'webrtc_enabled' => true
            ]);
        }

        $config = [
            'webrtc_id' => $profile->webrtc_id,
            'webrtc_enabled' => $profile->webrtc_enabled,
            'ice_servers' => [
                [
                    'urls' => config('webrtc.ice_servers.stun', ['stun:stun.l.google.com:19302']),
                ],
                [
                    'urls' => config('webrtc.ice_servers.turn', []),
                    'username' => config('webrtc.turn_username'),
                    'credential' => config('webrtc.turn_credential')
                ]
            ],
            'sip_gateway' => [
                'ws_url' => 'wss://' . env('SIP_DOMAIN', 'sip.example.com') . ':' . env('SIP_WEBSOCKET_PORT', 5066),
                'sip_username' => $profile->sip_username,
                'sip_password' => $profile->sip_password,
                'sip_domain' => $profile->sip_domain
            ],
            'media_constraints' => [
                'audio' => true,
                'video' => [
                    'width' => ['min' => 320, 'max' => 1280],
                    'height' => ['min' => 240, 'max' => 720],
                    'frameRate' => ['min' => 15, 'max' => 30]
                ]
            ],
            'codecs' => config('sip.codecs', [])
        ];

        return $this->sendResponse($config, 'WebRTC configuration retrieved successfully');
    }

    /**
     * Update WebRTC configuration
     */
    public function updateWebRtcConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'webrtc_enabled' => 'sometimes|boolean',
            'video_enabled' => 'sometimes|boolean',
            'audio_enabled' => 'sometimes|boolean',
            'default_device' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->firstOrFail();

        $data = $request->only([
            'webrtc_enabled',
            'video_enabled',
            'audio_enabled',
            'default_device'
        ]);

        // Filter out null values
        $data = array_filter($data, function($value) {
            return $value !== null;
        });

        $profile->update($data);

        return $this->sendResponse($profile, 'WebRTC configuration updated successfully');
    }

    /**
     * Generate WebRTC token for authentication
     */
    public function generateWebRtcToken(Request $request)
    {
        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->firstOrFail();

        $token = Str::random(64);
        $expires_at = now()->addHours(24);

        // In a real implementation, this would be stored in a tokens table
        // For now, we'll return a simple token structure

        $tokenData = [
            'token' => $token,
            'webrtc_id' => $profile->webrtc_id,
            'expires_at' => $expires_at->toDateTimeString(),
            'user_id' => $user->id,
            'permissions' => ['call', 'video', 'audio']
        ];

        return $this->sendResponse($tokenData, 'WebRTC token generated successfully');
    }

    /**
     * Log WebRTC call
     */
    public function logWebRtcCall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'caller_id' => 'required|string',
            'receiver_id' => 'required|string',
            'call_type' => 'required|in:incoming,outgoing,missed',
            'call_status' => 'required|in:completed,failed,missed',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date',
            'duration_seconds' => 'required|integer|min:0',
            'video_enabled' => 'sometimes|boolean',
            'audio_enabled' => 'sometimes|boolean',
            'ice_connection_state' => 'sometimes|string',
            'peer_connection_state' => 'sometimes|string',
            'notes' => 'nullable|string',
            'recording_url' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $callData = [
            'user_id' => $request->user()->id,
            'caller_id' => $request->caller_id,
            'receiver_id' => $request->receiver_id,
            'call_type' => $request->call_type,
            'call_status' => $request->call_status,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'duration_seconds' => $request->duration_seconds,
            'call_method' => 'webrtc',
            'notes' => $request->notes,
            'recording_url' => $request->recording_url
        ];

        // Add WebRTC specific metadata
        if ($request->has('video_enabled')) {
            $callData['metadata']['video_enabled'] = $request->video_enabled;
        }
        if ($request->has('audio_enabled')) {
            $callData['metadata']['audio_enabled'] = $request->audio_enabled;
        }
        if ($request->has('ice_connection_state')) {
            $callData['metadata']['ice_connection_state'] = $request->ice_connection_state;
        }
        if ($request->has('peer_connection_state')) {
            $callData['metadata']['peer_connection_state'] = $request->peer_connection_state;
        }

        $callLog = CallLog::create($callData);

        return $this->sendResponse($callLog, 'WebRTC call logged successfully');
    }

    /**
     * Get WebRTC call history
     */
    public function getWebRtcCallHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $query = CallLog::where('user_id', $request->user()->id)
            ->where('call_method', 'webrtc')
            ->orderBy('created_at', 'desc');

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $limit = $request->get('limit', 20);
        $calls = $query->paginate($limit);

        return $this->sendResponse($calls, 'WebRTC call history retrieved successfully');
    }

    /**
     * Get WebRTC statistics
     */
    public function getWebRtcStatistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_webrtc_calls' => CallLog::where('user_id', $user->id)
                ->where('call_method', 'webrtc')
                ->count(),
            'successful_calls' => CallLog::where('user_id', $user->id)
                ->where('call_method', 'webrtc')
                ->where('call_status', 'completed')
                ->count(),
            'failed_calls' => CallLog::where('user_id', $user->id)
                ->where('call_method', 'webrtc')
                ->where('call_status', 'failed')
                ->count(),
            'total_duration' => CallLog::where('user_id', $user->id)
                ->where('call_method', 'webrtc')
                ->sum('duration_seconds'),
            'video_calls' => CallLog::where('user_id', $user->id)
                ->where('call_method', 'webrtc')
                ->whereJsonContains('metadata->video_enabled', true)
                ->count(),
            'audio_only_calls' => CallLog::where('user_id', $user->id)
                ->where('call_method', 'webrtc')
                ->whereJsonContains('metadata->video_enabled', false)
                ->count()
        ];

        return $this->sendResponse($stats, 'WebRTC statistics retrieved successfully');
    }
}
