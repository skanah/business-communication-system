<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $table = 'unified_messages';

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'conversation_id',
        'campaign_id',
        'direction',
        'channel',
        'content',
        'subject',
        'attachments',
        'status',
        'message_id',
        'external_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'opened_at',
        'clicked_at',
        'click_count',
        'error_message',
        'error_details',
        'metadata',
        'ip_address',
        'user_agent',
        'sent_by_user_id',
    ];

    protected $casts = [
        'attachments' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'click_count' => 'integer',
        'error_details' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns this message
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the contact associated with this message
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the conversation this message belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the campaign this message belongs to (if any)
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the user who sent this message (for outbound messages)
     */
    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Mark message as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Update conversation last message time
        $this->conversation->addMessage($this);
    }

    /**
     * Mark message as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // Update contact last contacted time
        $this->contact->update(['last_contacted_at' => now()]);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Mark message as opened (for emails)
     */
    public function markAsOpened(): void
    {
        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }

    /**
     * Record a click (for emails)
     */
    public function recordClick(): void
    {
        $this->increment('click_count');

        if (!$this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }
    }

    /**
     * Mark message as failed
     */
    public function markAsFailed(string $errorMessage = null, array $errorDetails = null): void
    {
        $updates = [
            'status' => 'failed',
            'failed_at' => now(),
        ];

        if ($errorMessage) {
            $updates['error_message'] = $errorMessage;
        }

        if ($errorDetails) {
            $updates['error_details'] = $errorDetails;
        }

        $this->update($updates);
    }

    /**
     * Mark message as bounced
     */
    public function markAsBounced(): void
    {
        $this->update(['status' => 'bounced']);
    }

    /**
     * Check if message is inbound
     */
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Check if message is outbound
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    /**
     * Check if message was sent successfully
     */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered']);
    }

    /**
     * Check if message failed to send
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'bounced']);
    }

    /**
     * Check if message is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get delivery time in seconds
     */
    public function getDeliveryTimeAttribute(): ?int
    {
        if (!$this->sent_at || !$this->delivered_at) {
            return null;
        }

        return $this->sent_at->diffInSeconds($this->delivered_at);
    }

    /**
     * Get read time in seconds (from sent)
     */
    public function getReadTimeAttribute(): ?int
    {
        if (!$this->sent_at || !$this->read_at) {
            return null;
        }

        return $this->sent_at->diffInSeconds($this->read_at);
    }

    /**
     * Get time to first click in seconds
     */
    public function getTimeToFirstClickAttribute(): ?int
    {
        if (!$this->sent_at || !$this->clicked_at) {
            return null;
        }

        return $this->sent_at->diffInSeconds($this->clicked_at);
    }

    /**
     * Scope for inbound messages
     */
    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * Scope for outbound messages
     */
    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    /**
     * Scope for messages by channel
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for messages by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for sent messages
     */
    public function scopeSent($query)
    {
        return $query->whereIn('status', ['sent', 'delivered']);
    }

    /**
     * Scope for failed messages
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'bounced']);
    }

    /**
     * Scope for messages sent today
     */
    public function scopeSentToday($query)
    {
        return $query->whereDate('sent_at', today());
    }

    /**
     * Scope for messages sent within date range
     */
    public function scopeSentBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('sent_at', [$startDate, $endDate]);
    }
}
