<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SegmentController extends Controller
{
    /**
     * Display a listing of the segments.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $query = Segment::where('tenant_id', $tenantId);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_dynamic')) {
            $query->where('is_dynamic', filter_var($request->input('is_dynamic'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min($request->input('per_page', 15), 100); // Max 100 per page
        $segments = $query->paginate($perPage);

        return response()->json($this->respondWithPagination($segments));
    }

    /**
     * Store a newly created segment in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'rules' => 'required|array',
            'rules.all' => 'array',
            'rules.any' => 'array',
            'rules.none' => 'array',
            'is_active' => 'boolean',
            'is_dynamic' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $segmentData = $validator->validated();
        $segmentData['tenant_id'] = $tenantId;

        // Set defaults
        $segmentData['is_active'] = $segmentData['is_active'] ?? true;
        $segmentData['is_dynamic'] = $segmentData['is_dynamic'] ?? true;

        $segment = Segment::create($segmentData);

        // Recalculate contact count
        $segment->recalculateContactCount();

        return response()->json([
            'success' => true,
            'message' => 'Segment created successfully',
            'data' => $segment,
        ], 201);
    }

    /**
     * Display the specified segment.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $segment = Segment::where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $segment,
        ]);
    }

    /**
     * Update the specified segment in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $segment = Segment::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'rules' => 'sometimes|required|array',
            'rules.all' => 'array',
            'rules.any' => 'array',
            'rules.none' => 'array',
            'is_active' => 'boolean',
            'is_dynamic' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $segmentData = $validator->validated();

        $segment->update($segmentData);

        // Recalculate contact count if rules changed
        if (isset($segmentData['rules'])) {
            $segment->recalculateContactCount();
        }

        return response()->json([
            'success' => true,
            'message' => 'Segment updated successfully',
            'data' => $segment,
        ]);
    }

    /**
     * Remove the specified segment from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $segment = Segment::where('tenant_id', $tenantId)->findOrFail($id);

        $segment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Segment deleted successfully',
        ]);
    }

    /**
     * Preview contacts that match the segment rules.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $segment = Segment::where('tenant_id', $tenantId)->findOrFail($id);

        // Get sample contacts that match the segment rules
        $sampleSize = min($request->input('sample_size', 10), 100); // Max 100 samples
        $matchingContacts = $segment->getMatchingContacts($sampleSize);

        // Count total matching contacts
        $totalCount = $segment->countMatchingContacts();

        return response()->json([
            'success' => true,
            'data' => [
                'segment' => $segment,
                'preview_contacts' => $matchingContacts,
                'total_matching_count' => $totalCount,
                'sample_size' => $sampleSize,
            ],
        ]);
    }

    /**
     * Test segment rules against a specific contact.
     */
    public function testContact(Request $request, int $segmentId, int $contactId): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $segment = Segment::where('tenant_id', $tenantId)->findOrFail($segmentId);
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($contactId);

        $matches = $segment->matchesContact($contact);

        return response()->json([
            'success' => true,
            'data' => [
                'segment_id' => $segmentId,
                'contact_id' => $contactId,
                'matches' => $matches,
                'contact' => $contact,
                'segment_rules' => $segment->rules,
            ],
        ]);
    }

    /**
     * Recalculate the contact count for a segment.
     */
    public function recalculateCount(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $segment = Segment::where('tenant_id', $tenantId)->findOrFail($id);

        $segment->recalculateContactCount();

        return response()->json([
            'success' => true,
            'message' => 'Segment contact count recalculated successfully',
            'data' => [
                'segment' => $segment,
                'contact_count' => $segment->contact_count,
            ],
        ]);
    }
}