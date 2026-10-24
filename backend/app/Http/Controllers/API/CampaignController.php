<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Segment;
use App\Models\Workflow;
use App\Models\Contact;
use App\Services\CampaignScheduler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    protected $campaignScheduler;

    public function __construct(CampaignScheduler $campaignScheduler)
    {
        $this->campaignScheduler = $campaignScheduler;
    }

    /**
     * Display a listing of the campaigns.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $query = Campaign::where('tenant_id', $tenantId);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('segment_id')) {
            $query->where('segment_id', $request->input('segment_id'));
        }

        $perPage = min($request->input('per_page', 15), 100); // Max 100 per page
        $campaigns = $query->paginate($perPage);

        return response()->json($this->respondWithPagination($campaigns));
    }

    /**
     * Store a newly created campaign in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => ['required', Rule::in(['broadcast', 'drip', 'triggered'])],
            'segment_id' => 'nullable|exists:segments,id,tenant_id,' . $tenantId,
            'workflow_id' => 'required|exists:workflows,id,tenant_id,' . $tenantId,
            'scheduled_at' => 'nullable|date',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaignData = $validator->validated();
        $campaignData['tenant_id'] = $tenantId;

        // Set defaults
        $campaignData['is_active'] = $campaignData['is_active'] ?? true;
        $campaignData['status'] = ($campaignData['scheduled_at'] ?? null) ? 'scheduled' : 'draft';

        $campaign = Campaign::create($campaignData);

        // If scheduled, schedule the campaign
        if ($campaign->scheduled_at) {
            $this->campaignScheduler->scheduleCampaign($campaign, $campaign->scheduled_at);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign created successfully',
            'data' => $campaign,
        ], 201);
    }

    /**
     * Display the specified campaign.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->with(['segment', 'workflow', 'tenant'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $campaign,
        ]);
    }

    /**
     * Update the specified campaign in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => ['sometimes', 'required', Rule::in(['broadcast', 'drip', 'triggered'])],
            'segment_id' => 'nullable|exists:segments,id,tenant_id,' . $tenantId,
            'workflow_id' => 'sometimes|required|exists:workflows,id,tenant_id,' . $tenantId,
            'scheduled_at' => 'nullable|date',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'running', 'paused', 'completed', 'cancelled'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaignData = $validator->validated();

        $campaign->update($campaignData);

        return response()->json([
            'success' => true,
            'message' => 'Campaign updated successfully',
            'data' => $campaign,
        ]);
    }

    /**
     * Remove the specified campaign from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        // Cancel if scheduled
        if ($campaign->status === 'scheduled') {
            $this->campaignScheduler->cancelScheduledCampaign($campaign);
        }

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully',
        ]);
    }

    /**
     * Preview contacts that match the campaign's segment.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->with('segment')->findOrFail($id);

        if (!$campaign->segment) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign has no segment assigned for preview',
            ], 400);
        }

        // Get sample contacts that match the campaign's segment
        $sampleSize = min($request->input('sample_size', 10), 100); // Max 100 samples
        $matchingContacts = $campaign->segment->getMatchingContacts($sampleSize);

        // Count total matching contacts
        $totalCount = $campaign->segment->countMatchingContacts();

        return response()->json([
            'success' => true,
            'data' => [
                'campaign' => $campaign,
                'preview_contacts' => $matchingContacts,
                'total_matching_count' => $totalCount,
                'sample_size' => $sampleSize,
            ],
        ]);
    }

    /**
     * Test if a specific contact matches the campaign's segment.
     */
    public function testContact(Request $request, int $campaignId, int $contactId): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->with('segment')->findOrFail($campaignId);
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($contactId);

        $matches = false;
        $message = '';

        if ($campaign->segment) {
            $matches = $campaign->segment->matchesContact($contact);
            $message = $matches ? 'Contact matches campaign segment' : 'Contact does not match campaign segment';
        } else {
            $message = 'Campaign has no segment to match against';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'campaign_id' => $campaignId,
                'contact_id' => $contactId,
                'matches' => $matches,
                'message' => $message,
                'contact' => $contact,
                'campaign' => $campaign,
            ],
        ]);
    }

    /**
     * Recalculate the contact count for a campaign.
     */
    public function recalculateCount(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $campaign->recalculateContactCount();

        return response()->json([
            'success' => true,
            'message' => 'Campaign contact count recalculated successfully',
            'data' => [
                'campaign' => $campaign,
                'contact_count' => $campaign->contact_count,
            ],
        ]);
    }

    /**
     * Start a campaign manually.
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        if ($campaign->status === 'running') {
            return response()->json([
                'success' => false,
                'message' => 'Campaign is already running',
            ], 400);
        }

        // Change status to running and start execution
        $campaign->update(['status' => 'running']);
        $this->campaignScheduler->executeCampaign($campaign);

        return response()->json([
            'success' => true,
            'message' => 'Campaign started successfully',
            'data' => $campaign,
        ]);
    }

    /**
     * Pause a running campaign.
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        if ($campaign->status !== 'running') {
            return response()->json([
                'success' => false,
                'message' => 'Campaign is not currently running',
            ], 400);
        }

        $campaign->update(['status' => 'paused']);

        return response()->json([
            'success' => true,
            'message' => 'Campaign paused successfully',
            'data' => $campaign,
        ]);
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        if ($campaign->status !== 'paused') {
            return response()->json([
                'success' => false,
                'message' => 'Campaign is not paused',
            ], 400);
        }

        $campaign->update(['status' => 'running']);

        return response()->json([
            'success' => true,
            'message' => 'Campaign resumed successfully',
            'data' => $campaign,
        ]);
    }

    /**
     * Get campaign statistics.
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $stats = $campaign->getStats();
        $performance = $campaign->getPerformanceMetrics();

        return response()->json([
            'success' => true,
            'data' => [
                'campaign' => $campaign,
                'statistics' => $stats,
                'performance_metrics' => $performance,
            ],
        ]);
    }
}