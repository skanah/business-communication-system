<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'contact_id',
        'status',
        'current_node_id',
        'context',
        'node_states',
        'started_at',
        'completed_at',
        'next_execution_at',
        'error_message',
        'error_details',
    ];

    protected $casts = [
        'context' => 'array',
        'node_states' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_execution_at' => 'datetime',
        'error_details' => 'array',
    ];

    /**
     * Get the tenant that owns this execution
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the workflow being executed
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the contact this execution is for
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Mark execution as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark execution as failed
     */
    public function markAsFailed(string $errorMessage = null, array $errorDetails = null): void
    {
        $updates = [
            'status' => 'failed',
            'completed_at' => now(),
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
     * Pause execution
     */
    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    /**
     * Resume execution
     */
    public function resume(): void
    {
        $this->update(['status' => 'running']);
    }

    /**
     * Schedule next execution
     */
    public function scheduleNextExecution($nextExecutionAt): void
    {
        $this->update([
            'status' => 'waiting',
            'next_execution_at' => $nextExecutionAt,
        ]);
    }

    /**
     * Check if execution is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if execution is waiting
     */
    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    /**
     * Check if execution is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if execution is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get execution duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * Scope for running executions
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for waiting executions
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /**
     * Scope for completed executions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed executions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for executions ready for next run
     */
    public function scopeReadyForExecution($query)
    {
        return $query->where('status', 'waiting')
                    ->where('next_execution_at', '<=', now());
    }
}
