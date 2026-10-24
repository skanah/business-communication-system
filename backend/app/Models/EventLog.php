<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScopedModel;

class EventLog extends Model
{
    use HasFactory;
    use TenantScopedModel;

    protected $fillable = [
        'tenant_id',
        'event_type',
        'event_category',
        'user_id',
        'contact_id',
        'message_id',
        'campaign_id',
        'workflow_id',
        'conversation_id',
        'action',
        'status',
        'description',
        'event_data',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
        'error_message',
        'error_details',
    ];

    protected $casts = [
        'event_data' => 'array',
        'metadata' => 'array',
        'error_details' => 'array',
    ];

    /**
     * Get the tenant that owns this event log
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user associated with this event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contact associated with this event
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the message associated with this event
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the campaign associated with this event
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the workflow associated with this event
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the conversation associated with this event
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Log an event
     */
    public static function log(
        int $tenantId,
        string $eventType,
        string $action,
        array $data = []
    ): self {
        return static::create(array_merge([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'event_category' => explode('.', $eventType)[0] ?? 'system',
            'action' => $action,
        ], $data));
    }

    /**
     * Log a message event
     */
    public static function logMessageEvent(
        Message $message,
        string $action,
        array $additionalData = []
    ): self {
        return static::create(array_merge([
            'tenant_id' => $message->tenant_id,
            'event_type' => "message.{$action}",
            'event_category' => 'message',
            'message_id' => $message->id,
            'contact_id' => $message->contact_id,
            'conversation_id' => $message->conversation_id,
            'campaign_id' => $message->campaign_id,
            'action' => $action,
            'status' => 'success',
            'event_data' => [
                'channel' => $message->channel,
                'direction' => $message->direction,
            ],
        ], $additionalData));
    }

    /**
     * Log a campaign event
     */
    public static function logCampaignEvent(
        Campaign $campaign,
        string $action,
        array $additionalData = []
    ): self {
        return static::create(array_merge([
            'tenant_id' => $campaign->tenant_id,
            'event_type' => "campaign.{$action}",
            'event_category' => 'campaign',
            'campaign_id' => $campaign->id,
            'user_id' => $campaign->segment?->id ? null : null, // Could be set by context
            'action' => $action,
            'status' => 'success',
            'event_data' => [
                'campaign_type' => $campaign->type,
                'campaign_status' => $campaign->status,
            ],
        ], $additionalData));
    }

    /**
     * Log a contact event
     */
    public static function logContactEvent(
        Contact $contact,
        string $action,
        array $additionalData = []
    ): self {
        return static::create(array_merge([
            'tenant_id' => $contact->tenant_id,
            'event_type' => "contact.{$action}",
            'event_category' => 'contact',
            'contact_id' => $contact->id,
            'action' => $action,
            'status' => 'success',
            'event_data' => [
                'contact_status' => $contact->status,
            ],
        ], $additionalData));
    }

    /**
     * Scope for events by type
     */
    public function scopeByType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for events by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    /**
     * Scope for events by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for events within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for events today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for failed events
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failure');
    }

    /**
     * Scope for successful events
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
}
