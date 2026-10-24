<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'subject',
        'channel',
        'status',
        'priority',
        'assigned_user_id',
        'last_message_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'message_count',
        'agent_message_count',
        'contact_message_count',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'message_count' => 'integer',
        'agent_message_count' => 'integer',
        'contact_message_count' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns this conversation
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the contact associated with this conversation
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the assigned user for this conversation
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Get all messages in this conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the latest message in this conversation
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Assign conversation to a user
     */
    public function assignTo(User $user): void
    {
        $this->update(['assigned_user_id' => $user->id]);
    }

    /**
     * Unassign conversation
     */
    public function unassign(): void
    {
        $this->update(['assigned_user_id' => null]);
    }

    /**
     * Update conversation status
     */
    public function updateStatus(string $status): void
    {
        $updates = ['status' => $status];

        switch ($status) {
            case 'resolved':
                $updates['resolved_at'] = now();
                break;
            case 'closed':
                $updates['closed_at'] = now();
                break;
        }

        $this->update($updates);
    }

    /**
     * Add a message to the conversation
     */
    public function addMessage(Message $message): void
    {
        // Update message counts
        $this->increment('message_count');

        if ($message->direction === 'outbound') {
            $this->increment('agent_message_count');

            // Set first response time if this is the first agent response
            if (!$this->first_response_at) {
                $this->update(['first_response_at' => now()]);
            }
        } else {
            $this->increment('contact_message_count');
        }

        // Update last message timestamp
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Check if conversation is open
     */
    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'pending']);
    }

    /**
     * Check if conversation is resolved
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    /**
     * Check if conversation is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Get response time in minutes
     */
    public function getResponseTimeAttribute(): ?int
    {
        if (!$this->first_response_at) {
            return null;
        }

        return $this->first_response_at->diffInMinutes($this->created_at);
    }

    /**
     * Get resolution time in minutes
     */
    public function getResolutionTimeAttribute(): ?int
    {
        if (!$this->resolved_at) {
            return null;
        }

        return $this->resolved_at->diffInMinutes($this->created_at);
    }

    /**
     * Scope for open conversations
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'pending']);
    }

    /**
     * Scope for conversations by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for conversations by channel
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for conversations by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for assigned conversations
     */
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('assigned_user_id');
    }

    /**
     * Scope for unassigned conversations
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_user_id');
    }

    /**
     * Scope for conversations assigned to a specific user
     */
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }
}
