<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type',
        'segment_id',
        'workflow_id',
        'status',
        'is_active',
        'is_dynamic',
        'scheduled_at',
        'started_at',
        'completed_at',
        'settings',
        'total_contacts',
        'sent_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'replied_count',
        'bounced_count',
        'unsubscribed_count',
        'tags',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'settings' => 'array',
        'total_contacts' => 'integer',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'opened_count' => 'integer',
        'clicked_count' => 'integer',
        'replied_count' => 'integer',
        'bounced_count' => 'integer',
        'unsubscribed_count' => 'integer',
        'failed_count' => 'integer', // Added for tracking failed executions
        'tags' => 'array',
    ];

    /**
     * Get the tenant that owns this campaign
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the segment associated with this campaign
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * Get the workflow associated with this campaign
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get all messages sent by this campaign
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get all campaign logs
     */
    public function logs(): HasMany
    {
        return $this->hasMany(CampaignLog::class);
    }

    /**
     * Check if campaign is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['scheduled', 'running']);
    }

    /**
     * Check if campaign is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Start the campaign
     */
    public function start(): void
    {
        if ($this->status === 'scheduled' || $this->status === 'draft') {
            $this->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            // Trigger campaign execution logic
            $this->execute();
        }
    }

    /**
     * Pause the campaign
     */
    public function pause(): void
    {
        if ($this->status === 'running') {
            $this->update(['status' => 'paused']);
        }
    }

    /**
     * Resume the campaign
     */
    public function resume(): void
    {
        if ($this->status === 'paused') {
            $this->update(['status' => 'running']);
        }
    }

    /**
     * Complete the campaign
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancel the campaign
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Execute the campaign based on its type
     */
    private function execute(): void
    {
        switch ($this->type) {
            case 'broadcast':
                $this->executeBroadcastCampaign();
                break;
            case 'drip':
                $this->executeDripCampaign();
                break;
            case 'triggered':
                $this->executeTriggeredCampaign();
                break;
        }
    }

    /**
     * Execute broadcast campaign
     */
    public function executeBroadcastCampaign(): void
    {
        if (!$this->segment) {
            \Log::warning("Campaign {$this->id} has no segment assigned", [
                'campaign_id' => $this->id,
                'tenant_id' => $this->tenant_id
            ]);
            return;
        }

        // Get contacts from segment using the segment's matching logic
        $contacts = $this->segment->getMatchingContacts();
        $totalContacts = $contacts->count();

        $this->update(['total_contacts' => $totalContacts]);

        // Process contacts in batches to avoid memory issues
        $batchSize = $this->settings['batch_size'] ?? 50;
        $delayBetweenBatches = $this->settings['delay_between_batches'] ?? 0; // seconds

        $contacts->chunk($batchSize, function ($chunk) use ($delayBetweenBatches) {
            foreach ($chunk as $contact) {
                // Execute the associated workflow for this contact
                if ($this->workflow) {
                    try {
                        $this->workflow->executeForContact($contact, [
                            'campaign_id' => $this->id,
                            'campaign_name' => $this->name,
                        ]);

                        $this->increment('sent_count');
                    } catch (\Exception $e) {
                        \Log::error("Failed to execute workflow for contact in campaign", [
                            'campaign_id' => $this->id,
                            'contact_id' => $contact->id,
                            'error' => $e->getMessage(),
                        ]);

                        $this->increment('failed_count');
                    }
                }
            }

            // Add delay between batches if specified
            if ($delayBetweenBatches > 0) {
                sleep($delayBetweenBatches);
            }
        });

        // Mark campaign as completed after all contacts processed
        $this->complete();
    }

    /**
     * Execute drip campaign
     */
    public function executeDripCampaign(): void
    {
        if (!$this->segment) {
            \Log::warning("Drip campaign {$this->id} has no segment assigned", [
                'campaign_id' => $this->id,
                'tenant_id' => $this->tenant_id
            ]);
            return;
        }

        // Get contacts from segment
        $contacts = $this->segment->getMatchingContacts();
        $totalContacts = $contacts->count();

        $this->update(['total_contacts' => $totalContacts]);

        // For drip campaigns, we schedule workflow executions over time
        foreach ($contacts as $contact) {
            if ($this->workflow) {
                try {
                    // Execute the workflow which will handle delays internally
                    $this->workflow->executeForContact($contact, [
                        'campaign_id' => $this->id,
                        'campaign_name' => $this->name,
                    ]);

                    $this->increment('sent_count');
                } catch (\Exception $e) {
                    \Log::error("Failed to execute drip workflow for contact", [
                        'campaign_id' => $this->id,
                        'contact_id' => $contact->id,
                        'error' => $e->getMessage(),
                    ]);

                    $this->increment('failed_count');
                }
            }
        }

        // For drip campaigns, we don't immediately complete - they run over time
        // The campaign completion is handled when all workflow executions are done
    }

    /**
     * Execute triggered campaign
     */
    public function executeTriggeredCampaign(): void
    {
        if (!$this->workflow) {
            \Log::warning("Triggered campaign {$this->id} has no workflow assigned", [
                'campaign_id' => $this->id,
                'tenant_id' => $this->tenant_id
            ]);
            return;
        }

        // For triggered campaigns, we don't execute immediately
        // Instead, we set up listeners for the trigger events
        // The actual execution happens when the trigger occurs

        // Update campaign status to indicate it's active and listening for triggers
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        // Log that the campaign is active and listening
        \App\Models\CampaignLog::create([
            'tenant_id' => $this->tenant_id,
            'campaign_id' => $this->id,
            'contact_id' => null,
            'action' => 'triggered_campaign_activated',
            'status' => 'success',
            'event_data' => [
                'workflow_id' => $this->workflow_id,
                'trigger_type' => $this->settings['trigger_type'] ?? 'unknown',
            ],
        ]);
    }

    /**
     * Process a trigger event for a triggered campaign
     */
    public function processTriggerEvent(Contact $contact, array $context = []): bool
    {
        if ($this->type !== 'triggered') {
            return false;
        }

        // Check if the contact matches the campaign's segment (if any)
        if ($this->segment && !$this->segment->matchesContact($contact)) {
            return false;
        }

        // Execute the associated workflow for this contact
        if ($this->workflow) {
            try {
                $this->workflow->executeForContact($contact, array_merge([
                    'campaign_id' => $this->id,
                    'campaign_name' => $this->name,
                ], $context));

                $this->increment('sent_count');

                // Log the execution
                \App\Models\CampaignLog::create([
                    'tenant_id' => $this->tenant_id,
                    'campaign_id' => $this->id,
                    'contact_id' => $contact->id,
                    'action' => 'triggered_execution',
                    'status' => 'success',
                    'event_data' => $context,
                ]);

                return true;
            } catch (\Exception $e) {
                \Log::error("Failed to execute triggered campaign workflow", [
                    'campaign_id' => $this->id,
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);

                $this->increment('failed_count');

                // Log the failure
                \App\Models\CampaignLog::create([
                    'tenant_id' => $this->tenant_id,
                    'campaign_id' => $this->id,
                    'contact_id' => $contact->id,
                    'action' => 'triggered_execution_failed',
                    'status' => 'failure',
                    'error_message' => $e->getMessage(),
                    'event_data' => $context,
                ]);

                return false;
            }
        }

        return false;
    }

    /**
     * Schedule campaign execution
     */
    public function scheduleExecution(): void
    {
        if ($this->scheduled_at && $this->scheduled_at->isFuture()) {
            // The campaign is scheduled for the future
            // In a real application, this would use Laravel's task scheduling
            // or a job queue system to execute at the scheduled time
            $this->update(['status' => 'scheduled']);
        } else {
            // Execute immediately
            $this->start();
        }
    }

    /**
     * Check if campaign is ready to execute
     */
    public function isReadyToExecute(): bool
    {
        return $this->status === 'scheduled' &&
               $this->scheduled_at &&
               $this->scheduled_at->lte(now());
    }

    /**
     * Get campaign statistics
     */
    public function getStats(): array
    {
        return [
            'total_contacts' => $this->total_contacts,
            'sent' => $this->sent_count,
            'delivered' => $this->delivered_count,
            'opened' => $this->opened_count,
            'clicked' => $this->clicked_count,
            'replied' => $this->replied_count,
            'bounced' => $this->bounced_count,
            'unsubscribed' => $this->unsubscribed_count,
            'delivery_rate' => $this->delivery_rate,
            'open_rate' => $this->open_rate,
            'click_rate' => $this->click_rate,
            'reply_rate' => $this->reply_rate,
        ];
    }

    /**
     * Get campaign performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $stats = $this->getStats();

        return [
            'reach' => $stats['total_contacts'] > 0 ? round(($stats['sent'] / $stats['total_contacts']) * 100, 2) : 0,
            'delivery_rate' => $stats['delivery_rate'],
            'open_rate' => $stats['open_rate'],
            'click_rate' => $stats['click_rate'],
            'reply_rate' => $stats['reply_rate'],
            'conversion_rate' => $stats['total_contacts'] > 0 ? round(($stats['replied'] / $stats['total_contacts']) * 100, 2) : 0,
        ];
    }

    /**
     * Get campaign engagement timeline
     */
    public function getEngagementTimeline(string $period = 'day'): array
    {
        // This would aggregate campaign logs by time period
        $logs = \App\Models\CampaignLog::where('campaign_id', $this->id)
            ->selectRaw("
                DATE(created_at) as date,
                action,
                COUNT(*) as count
            ")
            ->groupBy(['date', 'action'])
            ->orderBy('date')
            ->get();

        // Format the data by date and action
        $timeline = [];
        foreach ($logs as $log) {
            $date = $log->date;
            if (!isset($timeline[$date])) {
                $timeline[$date] = [
                    'date' => $date,
                    'sent' => 0,
                    'delivered' => 0,
                    'opened' => 0,
                    'clicked' => 0,
                    'replied' => 0,
                ];
            }

            $timeline[$date][$log->action] = $log->count;
        }

        return array_values($timeline);
    }

    /**
     * Get top performing contacts for this campaign
     */
    public function getTopPerformingContacts(int $limit = 10): \Illuminate\Support\Collection
    {
        return \App\Models\CampaignLog::where('campaign_id', $this->id)
            ->join('contacts', 'campaign_logs.contact_id', '=', 'contacts.id')
            ->select('contacts.*', \DB::raw('COUNT(campaign_logs.id) as interactions'))
            ->groupBy('contacts.id')
            ->orderByDesc('interactions')
            ->limit($limit)
            ->get();
    }

    /**
     * Update campaign statistics
     */
    public function updateStatistics(array $stats): void
    {
        $this->increment('sent_count', $stats['sent'] ?? 0);
        $this->increment('delivered_count', $stats['delivered'] ?? 0);
        $this->increment('opened_count', $stats['opened'] ?? 0);
        $this->increment('clicked_count', $stats['clicked'] ?? 0);
        $this->increment('replied_count', $stats['replied'] ?? 0);
        $this->increment('bounced_count', $stats['bounced'] ?? 0);
        $this->increment('unsubscribed_count', $stats['unsubscribed'] ?? 0);
    }

    /**
     * Scope for active campaigns
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'running']);
    }

    /**
     * Scope for completed campaigns
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope by campaign type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
