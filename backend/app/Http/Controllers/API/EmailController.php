<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\CommunicationProfile;
use App\Models\EmailMessage;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Mail\SendEmail;

class EmailController extends BaseController
{
    /**
     * Get email configuration for authenticated user
     */
    public function getEmailConfiguration(Request $request)
    {
        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            $profile = CommunicationProfile::create([
                'user_id' => $user->id,
                'email_address' => $user->email,
                'email_notifications' => true
            ]);
        }

        $config = [
            'email_address' => $profile->email_address,
            'email_notifications' => $profile->email_notifications,
            'smtp_host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
            'smtp_port' => env('MAIL_PORT', 2525),
            'smtp_username' => env('MAIL_USERNAME'),
            'smtp_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'email_sender' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'email_sender_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            'daily_limit' => env('EMAIL_DAILY_LIMIT', 100),
            'attachment_limit' => env('EMAIL_ATTACHMENT_LIMIT', 10), // MB
            'cost_per_email' => env('EMAIL_COST_PER_MESSAGE', 0.001),
            'currency' => env('EMAIL_CURRENCY', 'USD')
        ];

        return $this->sendResponse($config, 'Email configuration retrieved successfully');
    }

    /**
     * Update email configuration
     */
    public function updateEmailConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_address' => 'sometimes|email',
            'email_notifications' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->firstOrFail();

        $data = $request->only([
            'email_address',
            'email_notifications'
        ]);

        // Filter out null values
        $data = array_filter($data, function($value) {
            return $value !== null;
        });

        $profile->update($data);

        return $this->sendResponse($profile, 'Email configuration updated successfully');
    }

    /**
     * Send email
     */
    public function sendEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'html_content' => 'sometimes|string',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'sometimes|file|max:10240' // 10MB
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $user = $request->user();
        $profile = CommunicationProfile::where('user_id', $user->id)->firstOrFail();

        // Calculate cost
        $costPerEmail = env('EMAIL_COST_PER_MESSAGE', 0.001);
        $attachmentCount = $request->has('attachments') ? count($request->attachments) : 0;
        $totalCost = $costPerEmail + ($attachmentCount * $costPerEmail * 0.5); // 50% extra per attachment

        // Check wallet balance
        $wallet = Wallet::where('user_id', $user->id)->first();
        if ($wallet && $wallet->balance < $totalCost) {
            return $this->sendError('Insufficient balance', [], 402);
        }

        // Check daily limit
        $dailySent = EmailMessage::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        if ($dailySent >= env('EMAIL_DAILY_LIMIT', 100)) {
            return $this->sendError('Daily email limit exceeded', [], 429);
        }

        // Store attachment files
        $attachmentFiles = [];
        $attachmentNames = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $filename = $attachment->getClientOriginalName();
                $path = $attachment->store('email_attachments', 'public');
                $attachmentFiles[] = storage_path('app/public/' . $path);
                $attachmentNames[] = $filename;
            }
        }

        // Prepare email data for sending
        $emailData = [
            'to' => $request->to,
            'subject' => $request->subject,
            'message' => $request->message,
            'html_content' => $request->html_content,
            'from' => $profile->email_address,
            'attachments' => $attachmentFiles
        ];

        try {
            // Send the email via SMTP (queued for better performance)
            Mail::to($request->to)->queue(new SendEmail($emailData));

            // Create email message record with success status
            $emailMessage = EmailMessage::create([
                'user_id' => $user->id,
                'sender_email' => $profile->email_address,
                'receiver_email' => $request->to,
                'subject' => $request->subject,
                'message_content' => $request->message,
                'html_content' => $request->html_content,
                'message_type' => 'outgoing',
                'message_status' => 'sent',
                'provider' => 'smtp',
                'provider_message_id' => 'SMTP-' . Str::upper(Str::random(16)),
                'attachments' => $attachmentNames,
                'size_bytes' => strlen($request->message) + ($attachmentCount * 1024 * 1024), // Approximate
                'cost' => $totalCost * 100, // Store in cents
                'currency' => env('EMAIL_CURRENCY', 'USD')
            ]);
        } catch (\Exception $e) {
            // Log the error and create failed message record
            Log::error('Email sending failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'to' => $request->to,
                'subject' => $request->subject,
                'error' => $e->getMessage()
            ]);

            $emailMessage = EmailMessage::create([
                'user_id' => $user->id,
                'sender_email' => $profile->email_address,
                'receiver_email' => $request->to,
                'subject' => $request->subject,
                'message_content' => $request->message,
                'html_content' => $request->html_content,
                'message_type' => 'outgoing',
                'message_status' => 'failed',
                'provider' => 'smtp',
                'provider_message_id' => null,
                'attachments' => $attachmentNames,
                'size_bytes' => strlen($request->message) + ($attachmentCount * 1024 * 1024), // Approximate
                'cost' => 0, // No cost charged for failed sends
                'currency' => env('EMAIL_CURRENCY', 'USD'),
                'error_message' => $e->getMessage()
            ]);

            return $this->sendError('Email sending failed: ' . $e->getMessage(), [], 500);
        }

        // Deduct from wallet if it exists
        if ($wallet) {
            $wallet->update([
                'balance' => $wallet->balance - $totalCost
            ]);

            // Log transaction
            Transaction::create([
                'wallet_id' => $wallet->id,
                'transaction_type' => 'payment',
                'service_type' => 'email',
                'amount' => $totalCost,
                'currency' => env('EMAIL_CURRENCY', 'USD'),
                'status' => 'completed',
                'description' => 'Email to ' . $request->to,
                'reference' => 'EMAIL-' . Str::upper(Str::random(12)),
                'metadata' => [
                    'email_id' => $emailMessage->id,
                    'receiver' => $request->to,
                    'subject' => $request->subject,
                    'attachment_count' => $attachmentCount
                ]
            ]);
        }

        return $this->sendResponse($emailMessage, 'Email sent successfully');
    }

    /**
     * Get email message history
     */
    public function getEmailHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'message_type' => 'sometimes|in:incoming,outgoing',
            'message_status' => 'sometimes|in:sent,delivered,failed,read',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'sender_email' => 'sometimes|email',
            'receiver_email' => 'sometimes|email'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        $query = EmailMessage::where('user_id', $request->user()->id)
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

        if ($request->has('sender_email')) {
            $query->where('sender_email', 'like', '%' . $request->sender_email . '%');
        }

        if ($request->has('receiver_email')) {
            $query->where('receiver_email', 'like', '%' . $request->receiver_email . '%');
        }

        $limit = $request->get('limit', 20);
        $messages = $query->paginate($limit);

        return $this->sendResponse($messages, 'Email message history retrieved successfully');
    }

    /**
     * Get email statistics
     */
    public function getEmailStatistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_emails' => EmailMessage::where('user_id', $user->id)->count(),
            'outgoing_emails' => EmailMessage::where('user_id', $user->id)
                ->where('message_type', 'outgoing')
                ->count(),
            'incoming_emails' => EmailMessage::where('user_id', $user->id)
                ->where('message_type', 'incoming')
                ->count(),
            'total_cost' => EmailMessage::where('user_id', $user->id)
                ->sum('cost') / 100, // Convert from cents
            'total_size' => EmailMessage::where('user_id', $user->id)
                ->sum('size_bytes'),
            'emails_with_attachments' => EmailMessage::where('user_id', $user->id)
                ->whereNotNull('attachments')
                ->count(),
            'daily_sent_today' => EmailMessage::where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->count()
        ];

        return $this->sendResponse($stats, 'Email statistics retrieved successfully');
    }

    /**
     * Get email by ID
     */
    public function getEmailById(Request $request, $id)
    {
        $email = EmailMessage::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$email) {
            return $this->sendError('Email not found', [], 404);
        }

        return $this->sendResponse($email, 'Email retrieved successfully');
    }

    /**
     * Get email attachments
     */
    public function getEmailAttachments(Request $request, $id)
    {
        $email = EmailMessage::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$email || !$email->attachments) {
            return $this->sendError('No attachments found', [], 404);
        }

        $attachments = array_map(function($attachment) {
            return [
                'name' => $attachment,
                'url' => asset('storage/' . $attachment),
                'size' => filesize(storage_path('app/public/' . $attachment))
            ];
        }, $email->attachments);

        return $this->sendResponse($attachments, 'Email attachments retrieved successfully');
    }
}
