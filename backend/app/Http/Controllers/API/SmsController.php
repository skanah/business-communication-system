<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\CommunicationProfile;
use App\Models\SmsMessage;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SmsController extends BaseController
{
    /**
     * Get SMS configuration for authenticated user
     */
    public function getSmsConfiguration(Request $request)
    {
        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            $profile = CommunicationProfile::create([
                'user_id' => $user->id,
                'sms_number' => '+1' . str_pad(rand(0, 999999999), 10, '0', STR_PAD_LEFT),
                'sms_notifications' => true
            ]);
        }

        $config = [
            'sms_number' => $profile->sms_number,
            'sms_notifications' => $profile->sms_notifications,
            'sms_provider' => env('SMS_PROVIDER', 'smppe'),
            'sms_api_endpoint' => env('SMS_API_ENDPOINT', 'https://sms-gateway.example.com/api'),
            'sms_api_key' => env('SMS_API_KEY', 'your-api-key'),
            'sms_sender_id' => env('SMS_SENDER_ID', 'COMMUNIC'),
            'character_limit' => env('SMS_CHARACTER_LIMIT', 160),
            'cost_per_sms' => env('SMS_COST_PER_MESSAGE', 0.01),
            'currency' => env('SMS_CURRENCY', 'USD')
        ];

        return $this->sendResponse($config, 'SMS configuration retrieved successfully');
    }

    /**
     * Update SMS configuration
     */
    public function updateSmsConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sms_number' => 'sometimes|string|regex:/^\+[1-9]\d{1,14}$/',
            'sms_notifications' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->firstOrFail();

        $data = $request->only([
            'sms_number',
            'sms_notifications'
        ]);

        // Filter out null values
        $data = array_filter($data, function($value) {
            return $value !== null;
        });

        $profile->update($data);

        return $this->sendResponse($profile, 'SMS configuration updated successfully');
    }

    /**
     * Send SMS message
     */
    public function sendSms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_number' => 'required|string|regex:/^\+[1-9]\d{1,14}$/',
            'message_content' => 'required|string|max:1600',
            'sender_id' => 'sometimes|string|max:11'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->firstOrFail();

        // Calculate cost
        $messageLength = strlen($request->message_content);
        $messageCount = ceil($messageLength / 160); // GSM 7-bit encoding
        $costPerMessage = env('SMS_COST_PER_MESSAGE', 0.01);
        $totalCost = $messageCount * $costPerMessage;

        // Check wallet balance
        $wallet = Wallet::where('user_id', $user->id)->first();
        if ($wallet && $wallet->balance < $totalCost) {
            return $this->sendError('Insufficient balance', [], 402);
        }

        // In a real implementation, this would send the SMS via SMPP or HTTP API
        // For now, we'll simulate a successful send and log the message

        $smsMessage = SmsMessage::create([
            'user_id' => $user->id,
            'sender_number' => $request->sender_id ?? $profile->sms_number,
            'receiver_number' => $request->receiver_number,
            'message_content' => $request->message_content,
            'message_type' => 'outgoing',
            'message_status' => 'sent',
            'provider' => env('SMS_PROVIDER', 'smppe'),
            'provider_message_id' => 'SMPP-' . Str::upper(Str::random(16)),
            'cost' => $totalCost * 100, // Store in cents
            'currency' => env('SMS_CURRENCY', 'USD')
        ]);

        // Deduct from wallet if it exists
        if ($wallet) {
            $wallet->update([
                'balance' => $wallet->balance - $totalCost
            ]);

            // Log transaction
            Transaction::create([
                'wallet_id' => $wallet->id,
                'transaction_type' => 'payment',
                'service_type' => 'sms',
                'amount' => $totalCost,
                'currency' => env('SMS_CURRENCY', 'USD'),
                'status' => 'completed',
                'description' => 'SMS message to ' . $request->receiver_number,
                'reference' => 'SMS-' . Str::upper(Str::random(12)),
                'metadata' => [
                    'message_id' => $smsMessage->id,
                    'receiver' => $request->receiver_number,
                    'message_count' => $messageCount
                ]
            ]);
        }

        return $this->sendResponse($smsMessage, 'SMS message sent successfully');
    }

    /**
     * Get SMS message history
     */
    public function getSmsHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'message_type' => 'sometimes|in:incoming,outgoing',
            'message_status' => 'sometimes|in:sent,delivered,failed,read',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'sender_number' => 'sometimes|string',
            'receiver_number' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $query = SmsMessage::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('message_type')) {
            $query->where('message_type', $request->message_type);
        }

        if ($request->has('message_status')) {
            $query->where('message_status', $request->message_status);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('sender_number')) {
            $query->where('sender_number', 'like', '%' . $request->sender_number . '%');
        }

        if ($request->has('receiver_number')) {
            $query->where('receiver_number', 'like', '%' . $request->receiver_number . '%');
        }

        $limit = $request->get('limit', 20);
        $messages = $query->paginate($limit);

        return $this->sendResponse($messages, 'SMS message history retrieved successfully');
    }

    /**
     * Get SMS statistics
     */
    public function getSmsStatistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_messages' => SmsMessage::where('user_id', $user->id)->count(),
            'outgoing_messages' => SmsMessage::where('user_id', $user->id)
                ->where('message_type', 'outgoing')
                ->count(),
            'incoming_messages' => SmsMessage::where('user_id', $user->id)
                ->where('message_type', 'incoming')
                ->count(),
            'total_cost' => SmsMessage::where('user_id', $user->id)
                ->sum('cost') / 100, // Convert from cents
            'successful_messages' => SmsMessage::where('user_id', $user->id)
                ->whereIn('message_status', ['sent', 'delivered', 'read'])
                ->count(),
            'failed_messages' => SmsMessage::where('user_id', $user->id)
                ->where('message_status', 'failed')
                ->count()
        ];

        return $this->sendResponse($stats, 'SMS statistics retrieved successfully');
    }

    /**
     * Get SMS delivery reports
     */
    public function getSmsDeliveryReports(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'sometimes|string',
            'status' => 'sometimes|in:delivered,failed,read',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $query = SmsMessage::where('user_id', $request->user()->id);

        if ($request->has('message_id')) {
            $query->where('id', $request->message_id);
        }

        if ($request->has('status')) {
            $query->where('message_status', $request->status);
        } else {
            $query->whereIn('message_status', ['delivered', 'failed', 'read']);
        }

        $limit = $request->get('limit', 20);
        $reports = $query->paginate($limit);

        return $this->sendResponse($reports, 'SMS delivery reports retrieved successfully');
    }
}
