<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\CommunicationProfile;
use App\Models\CallLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SipController extends BaseController
{
    /**
     * Get SIP configuration for authenticated user
     */
    public function getSipConfiguration(Request $request)
    {
        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            $profile = CommunicationProfile::create([
                'user_id' => $user->id,
                'sip_username' => 'user_' . $user->id,
                'sip_domain' => env('SIP_DOMAIN', 'sip.example.com'),
                'sip_password' => bin2hex(random_bytes(8))
            ]);
        }

        $config = [
            'sip_username' => $profile->sip_username,
            'sip_password' => $profile->sip_password,
            'sip_domain' => $profile->sip_domain,
            'sip_server' => env('SIP_SERVER', 'sip.example.com'),
            'sip_port' => env('SIP_PORT', 5060),
            'transport' => env('SIP_TRANSPORT', 'udp'),
            'webrtc_enabled' => $profile->webrtc_enabled,
            'call_forwarding' => $profile->call_forwarding_enabled ? $profile->call_forwarding_number : null,
            'voicemail' => $profile->voicemail_enabled ? [
                'enabled' => true,
                'pin' => $profile->voicemail_pin
            ] : null
        ];

        return $this->sendResponse($config, 'SIP configuration retrieved successfully');
    }

    /**
     * Update SIP configuration
     */
    public function updateSipConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sip_password' => 'sometimes|string|min:6',
            'call_forwarding_enabled' => 'sometimes|boolean',
            'call_forwarding_number' => 'sometimes|string',
            'voicemail_enabled' => 'sometimes|boolean',
            'voicemail_pin' => 'sometimes|string|min:4|max:8',
            'webrtc_enabled' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->firstOrFail();

        $data = $request->only([
            'sip_password',
            'call_forwarding_enabled',
            'call_forwarding_number',
            'voicemail_enabled',
            'voicemail_pin',
            'webrtc_enabled'
        ]);

        // Filter out null values
        $data = array_filter($data, function($value) {
            return $value !== null;
        });

        $profile->update($data);

        return $this->sendResponse($profile, 'SIP configuration updated successfully');
    }

    /**
     * Log a call
     */
    public function logCall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'caller_id' => 'required|string',
            'receiver_id' => 'required|string',
            'call_type' => 'required|in:incoming,outgoing,missed',
            'call_status' => 'required|in:completed,failed,missed',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date',
            'duration_seconds' => 'required|integer|min:0',
            'call_method' => 'required|in:sip,webrtc,pstn',
            'notes' => 'nullable|string',
            'recording_url' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $callLog = CallLog::create([
            'user_id' => $request->user()->id,
            ...$request->all()
        ]);

        return $this->sendResponse($callLog, 'Call logged successfully');
    }

    /**
     * Get call history
     */
    public function getCallHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'call_type' => 'sometimes|in:incoming,outgoing,missed',
            'call_status' => 'sometimes|in:completed,failed,missed',
            'call_method' => 'sometimes|in:sip,webrtc,pstn',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $query = CallLog::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('call_type')) {
            $query->where('call_type', $request->call_type);
        }

        if ($request->has('call_status')) {
            $query->where('call_status', $request->call_status);
        }

        if ($request->has('call_method')) {
            $query->where('call_method', $request->call_method);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $limit = $request->get('limit', 20);
        $calls = $query->paginate($limit);

        return $this->sendResponse($calls, 'Call history retrieved successfully');
    }

    /**
     * Get call statistics
     */
    public function getCallStatistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_calls' => CallLog::where('user_id', $user->id)->count(),
            'incoming_calls' => CallLog::where('user_id', $user->id)->where('call_type', 'incoming')->count(),
            'outgoing_calls' => CallLog::where('user_id', $user->id)->where('call_type', 'outgoing')->count(),
            'missed_calls' => CallLog::where('user_id', $user->id)->where('call_type', 'missed')->count(),
            'total_duration' => CallLog::where('user_id', $user->id)->sum('duration_seconds'),
            'sip_calls' => CallLog::where('user_id', $user->id)->where('call_method', 'sip')->count(),
            'webrtc_calls' => CallLog::where('user_id', $user->id)->where('call_method', 'webrtc')->count(),
            'pstn_calls' => CallLog::where('user_id', $user->id)->where('call_method', 'pstn')->count()
        ];

        return $this->sendResponse($stats, 'Call statistics retrieved successfully');
    }
}
