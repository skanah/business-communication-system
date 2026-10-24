<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\SmsMessage;
use App\Models\EmailMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;

class UnifiedInboxController extends BaseController
{
    /**
     * Get unified inbox messages
     */
    public function getMessages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'message_type' => 'sometimes|in:incoming,outgoing',
            'message_status' => 'sometimes|in:sent,delivered,failed,read',
            'channel' => 'sometimes|in:sms,email',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|in:created_at,updated_at',
            'sort_order' => 'sometimes|in:asc,desc'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $user = $request->user();
        $limit = $request->get('limit', 20);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        // Build queries for SMS and Email messages
        $smsQuery = SmsMessage::where('user_id', $user->id);
        $emailQuery = EmailMessage::where('user_id', $user->id);

        // Apply common filters
        $this->applyCommonFilters($smsQuery, $emailQuery, $request);

        // Get messages with pagination info
        $smsMessages = $smsQuery->get();
        $emailMessages = $emailQuery->get();

        // Combine and normalize messages
        $allMessages = $this->combineAndNormalizeMessages($smsMessages, $emailMessages);

        // Apply channel filter (sms or email) on the combined collection
        if ($request->has('channel')) {
            $channel = strtolower($request->input('channel'));
            $allMessages = $allMessages->filter(function ($message) use ($channel) {
                return ($message->_type === 'sms' && $channel === 'sms')
                    || ($message->_type === 'email' && $channel === 'email');
            })->values();
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allMessages = $this->sortMessages($allMessages, $sortBy, $sortOrder);

        // Apply pagination
        $totalMessages = $allMessages->count();
        $paginatedMessages = $allMessages->slice($offset, $limit)->values();

        // Add normalized data for each message
        $normalizedMessages = $paginatedMessages->map(function ($message) {
            return $this->normalizeMessage($message);
        });

        $pagination = [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $totalMessages,
            'last_page' => ceil($totalMessages / $limit),
            'from' => $totalMessages > 0 ? $offset + 1 : null,
            'to' => $totalMessages > 0 ? min($offset + $limit, $totalMessages) : null
        ];

        return $this->sendResponse([
            'messages' => $normalizedMessages,
            'pagination' => $pagination
        ], 'Unified inbox messages retrieved successfully');
    }

    /**
     * Get message by ID from unified inbox
     */
    public function getMessageById(Request $request, $id)
    {
        $user = $request->user();

        // Try to find SMS message first
        $smsMessage = SmsMessage::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($smsMessage) {
            return $this->sendResponse($this->normalizeMessage($smsMessage), 'Message retrieved successfully');
        }

        // Try to find Email message
        $emailMessage = EmailMessage::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($emailMessage) {
            return $this->sendResponse($this->normalizeMessage($emailMessage), 'Message retrieved successfully');
        }

        return $this->sendError('Message not found', [], 404);
    }

    /**
     * Get unified inbox statistics
     */
    public function getStatistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_messages' => SmsMessage::where('user_id', $user->id)->count() + EmailMessage::where('user_id', $user->id)->count(),
            'sms_messages' => [
                'total' => SmsMessage::where('user_id', $user->id)->count(),
                'incoming' => SmsMessage::where('user_id', $user->id)->where('message_type', 'incoming')->count(),
                'outgoing' => SmsMessage::where('user_id', $user->id)->where('message_type', 'outgoing')->count(),
                'total_cost' => SmsMessage::where('user_id', $user->id)->sum('cost') / 100
            ],
            'email_messages' => [
                'total' => EmailMessage::where('user_id', $user->id)->count(),
                'incoming' => EmailMessage::where('user_id', $user->id)->where('message_type', 'incoming')->count(),
                'outgoing' => EmailMessage::where('user_id', $user->id)->where('message_type', 'outgoing')->count(),
                'total_cost' => EmailMessage::where('user_id', $user->id)->sum('cost') / 100,
                'with_attachments' => EmailMessage::where('user_id', $user->id)->whereNotNull('attachments')->count()
            ],
            'unread_messages' => SmsMessage::where('user_id', $user->id)->where('message_status', 'received')->count() +
                                EmailMessage::where('user_id', $user->id)->where('message_status', 'received')->count(),
            'failed_messages' => SmsMessage::where('user_id', $user->id)->where('message_status', 'failed')->count() +
                                EmailMessage::where('user_id', $user->id)->where('message_status', 'failed')->count(),
            'total_cost' => (SmsMessage::where('user_id', $user->id)->sum('cost') + EmailMessage::where('user_id', $user->id)->sum('cost')) / 100,
            'messages_today' => SmsMessage::where('user_id', $user->id)->whereDate('created_at', today())->count() +
                               EmailMessage::where('user_id', $user->id)->whereDate('created_at', today())->count()
        ];

        return $this->sendResponse($stats, 'Unified inbox statistics retrieved successfully');
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        // Try to find and update SMS message
        $smsMessage = SmsMessage::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($smsMessage) {
            $smsMessage->update(['message_status' => 'read']);
            return $this->sendResponse($this->normalizeMessage($smsMessage), 'Message marked as read');
        }

        // Try to find and update Email message
        $emailMessage = EmailMessage::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($emailMessage) {
            $emailMessage->update(['message_status' => 'read']);
            return $this->sendResponse($this->normalizeMessage($emailMessage), 'Message marked as read');
        }

        return $this->sendError('Message not found', [], 404);
    }

    /**
     * Delete message from unified inbox
     */
    public function deleteMessage(Request $request, $id)
    {
        $user = $request->user();

        // Try to find and delete SMS message
        $smsMessage = SmsMessage::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($smsMessage) {
            $smsMessage->delete();
            return $this->sendResponse(['id' => $id], 'Message deleted successfully');
        }

        // Try to find and delete Email message
        $emailMessage = EmailMessage::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($emailMessage) {
            $emailMessage->delete();
            return $this->sendResponse(['id' => $id], 'Message deleted successfully');
        }

        return $this->sendError('Message not found', [], 404);
    }

    /**
     * Apply common filters to both SMS and Email queries
     */
    private function applyCommonFilters($smsQuery, $emailQuery, Request $request)
    {
        // Message type filter
        if ($request->has('message_type')) {
            $smsQuery->where('message_type', $request->message_type);
            $emailQuery->where('message_type', $request->message_type);
        }

        // Message status filter
        if ($request->has('message_status')) {
            $smsQuery->where('message_status', $request->message_status);
            $emailQuery->where('message_status', $request->message_status);
        }

        // Date range filters
        if ($request->has('start_date')) {
            $smsQuery->whereDate('created_at', '>=', $request->start_date);
            $emailQuery->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $smsQuery->whereDate('created_at', '<=', $request->end_date);
            $emailQuery->whereDate('created_at', '<=', $request->end_date);
        }

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $smsQuery->where(function($q) use ($search) {
                $q->where('message_content', 'like', '%' . $search . '%')
                  ->orWhere('sender_number', 'like', '%' . $search . '%')
                  ->orWhere('receiver_number', 'like', '%' . $search . '%');
            });

            $emailQuery->where(function($q) use ($search) {
                $q->where('message_content', 'like', '%' . $search . '%')
                  ->orWhere('subject', 'like', '%' . $search . '%')
                  ->orWhere('sender_email', 'like', '%' . $search . '%')
                  ->orWhere('receiver_email', 'like', '%' . $search . '%');
            });
        }

        // Channel filter
        if ($request->has('channel')) {
            // If filtering by channel, we'll handle this in the combine function
        }
    }

    /**
     * Combine and normalize SMS and Email messages
     */
    private function combineAndNormalizeMessages(Collection $smsMessages, Collection $emailMessages)
    {
        $allMessages = collect();

        // Add type identifier and normalize SMS messages
        foreach ($smsMessages as $message) {
            $message->_type = 'sms';
            $allMessages->push($message);
        }

        // Add type identifier and normalize Email messages
        foreach ($emailMessages as $message) {
            $message->_type = 'email';
            $allMessages->push($message);
        }

        return $allMessages;
    }

    /**
     * Sort messages by specified field and order
     */
    private function sortMessages(Collection $messages, string $sortBy, string $sortOrder)
    {
        return $messages->sortBy($sortBy, SORT_REGULAR, $sortOrder === 'desc')->values();
    }

    /**
     * Normalize message data for unified response
     */
    private function normalizeMessage($message)
    {
        $normalized = [
            'id' => $message->id,
            'type' => $message->_type,
            'message_type' => $message->message_type,
            'message_status' => $message->message_status,
            'content' => $message->message_content,
            'cost' => ($message->cost ?? 0) / 100,
            'currency' => $message->currency ?? 'USD',
            'provider' => $message->provider,
            'provider_message_id' => $message->provider_message_id,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
        ];

        if ($message->_type === 'sms') {
            $normalized['channel'] = 'SMS';
            $normalized['sender'] = $message->sender_number;
            $normalized['receiver'] = $message->receiver_number;
            $normalized['subject'] = 'SMS Message';
            $normalized['has_attachments'] = false;
            $normalized['size_bytes'] = strlen($message->message_content);
        } else {
            $normalized['channel'] = 'Email';
            $normalized['sender'] = $message->sender_email;
            $normalized['receiver'] = $message->receiver_email;
            $normalized['subject'] = $message->subject ?? 'No Subject';
            $normalized['html_content'] = $message->html_content;
            $normalized['has_attachments'] = !empty($message->attachments);
            $normalized['attachments'] = $message->attachments;
            $normalized['size_bytes'] = $message->size_bytes ?? strlen($message->message_content);
            $normalized['error_message'] = $message->error_message;
        }

        return $normalized;
    }
}
