<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Carbon;

class CampaignScheduler
{
    /**
     * Process scheduled campaigns that are ready to execute
     */
    public function processScheduledCampaigns(): array
    {
        $processed = 0;
        $errors = 0;

        // Get all campaigns that are scheduled and ready to execute
        $campaigns = Campaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($campaigns as $campaign) {
            try {
                $this->executeCampaign($campaign);
                $processed++;
            } catch (\Exception $e) {
                \Log::error("Failed to execute scheduled campaign", [
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $campaign->tenant_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errors++;
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
            'total_scheduled' => $campaigns->count(),
        ];
    }

    /**
     * Execute a specific campaign
     */
    public function executeCampaign(Campaign $campaign): void
    {
        // Update campaign status to running
        $campaign->update(['status' => 'running']);

        // Execute based on campaign type
        switch ($campaign->type) {
            case 'broadcast':
                $campaign->executeBroadcastCampaign();
                break;
            case 'drip':
                $campaign->executeDripCampaign();
                break;
            case 'triggered':
                $campaign->executeTriggeredCampaign();
                break;
            default:
                throw new \Exception("Unknown campaign type: {$campaign->type}");
        }

        // Update campaign status to completed if it's a one-time campaign
        if (in_array($campaign->type, ['broadcast', 'drip'])) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Schedule a campaign for future execution
     */
    public function scheduleCampaign(Campaign $campaign, Carbon $scheduledAt): void
    {
        $campaign->update([
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
        ]);
    }

    /**
     * Process triggered campaigns based on events
     */
    public function processTriggeredCampaigns(string $triggerType, array $context = []): void
    {
        // Get all active triggered campaigns that match the trigger type
        $campaigns = Campaign::where('type', 'triggered')
            ->where('status', 'running')
            ->whereJsonContains('settings->trigger_type', $triggerType)
            ->get();

        foreach ($campaigns as $campaign) {
            // Check if the campaign's segment matches the context
            // This would typically involve checking if the event is related to a contact in the segment
            if (isset($context['contact_id'])) {
                $contact = \App\Models\Contact::find($context['contact_id']);
                if ($contact && $campaign->segment) {
                    // Check if contact matches segment rules
                    if ($campaign->segment->matchesContact($contact)) {
                        // Process the trigger event for this campaign
                        $campaign->processTriggerEvent($contact, $context);
                    }
                }
            }
        }
    }

    /**
     * Get upcoming scheduled campaigns
     */
    public function getUpcomingScheduledCampaigns(int $limit = 10): \Illuminate\Support\Collection
    {
        return Campaign::where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get campaigns that are currently running
     */
    public function getRunningCampaigns(): \Illuminate\Support\Collection
    {
        return Campaign::where('status', 'running')
            ->get();
    }

    /**
     * Cancel a scheduled campaign
     */
    public function cancelScheduledCampaign(Campaign $campaign): void
    {
        if ($campaign->status === 'scheduled') {
            $campaign->update(['status' => 'cancelled']);
        }
    }

    /**
     * Resume a paused campaign
     */
    public function resumePausedCampaign(Campaign $campaign): void
    {
        if ($campaign->status === 'paused') {
            $campaign->update(['status' => 'running']);
        }
    }

    /**
     * Process recurring campaigns (if any)
     */
    public function processRecurringCampaigns(): array
    {
        $processed = 0;
        $errors = 0;

        // Get campaigns with recurrence settings
        $campaigns = Campaign::where('status', 'active')
            ->whereNotNull('settings->recurrence')
            ->get();

        foreach ($campaigns as $campaign) {
            try {
                // Check if it's time to run the recurring campaign again
                if ($this->shouldRunRecurringCampaign($campaign)) {
                    // Clone the campaign and schedule a new execution
                    $this->executeCampaign(clone $campaign);
                    $processed++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to execute recurring campaign", [
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $campaign->tenant_id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
        ];
    }

    /**
     * Check if a recurring campaign should run again
     */
    private function shouldRunRecurringCampaign(Campaign $campaign): bool
    {
        $settings = $campaign->settings;
        if (!isset($settings['recurrence'])) {
            return false;
        }

        $recurrence = $settings['recurrence'];
        $lastRun = $campaign->completed_at ?? $campaign->created_at;

        switch ($recurrence['frequency'] ?? 'daily') {
            case 'daily':
                return $lastRun->addDay()->isPast();
            case 'weekly':
                return $lastRun->addWeek()->isPast();
            case 'monthly':
                return $lastRun->addMonth()->isPast();
            case 'custom':
                $interval = $recurrence['interval'] ?? 1;
                $unit = $recurrence['unit'] ?? 'days';
                $method = 'add' . ucfirst($unit);
                return $lastRun->$method($interval)->isPast();
            default:
                return false;
        }
    }
}