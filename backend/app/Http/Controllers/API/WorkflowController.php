<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the workflows.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $query = Workflow::where('tenant_id', $tenantId);

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
        $workflows = $query->paginate($perPage);

        return response()->json($this->respondWithPagination($workflows));
    }

    /**
     * Store a newly created workflow in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'graph' => 'required|array',
            'graph.nodes' => 'required|array',
            'graph.edges' => 'required|array',
            'node_types' => 'required|array',
            'is_active' => 'boolean',
            'is_dynamic' => 'boolean',
            'settings' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workflowData = $validator->validated();
        $workflowData['tenant_id'] = $tenantId;

        // Set defaults
        $workflowData['is_active'] = $workflowData['is_active'] ?? true;
        $workflowData['is_dynamic'] = $workflowData['is_dynamic'] ?? true;

        // Validate workflow structure
        $workflow = new Workflow($workflowData);
        $validationErrors = $workflow->validate();
        
        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Workflow validation failed',
                'errors' => $validationErrors,
            ], 422);
        }

        $workflow = Workflow::create($workflowData);

        return response()->json([
            'success' => true,
            'message' => 'Workflow created successfully',
            'data' => $workflow,
        ], 201);
    }

    /**
     * Display the specified workflow.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $workflow,
        ]);
    }

    /**
     * Update the specified workflow in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'graph' => 'sometimes|required|array',
            'graph.nodes' => 'array',
            'graph.edges' => 'array',
            'node_types' => 'array',
            'is_active' => 'boolean',
            'is_dynamic' => 'boolean',
            'settings' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workflowData = $validator->validated();

        // If graph is being updated, validate the workflow structure
        if (isset($workflowData['graph'])) {
            $tempWorkflow = new Workflow(array_merge($workflow->toArray(), $workflowData));
            $validationErrors = $tempWorkflow->validate();
            
            if (!empty($validationErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow validation failed',
                    'errors' => $validationErrors,
                ], 422);
            }
        }

        $workflow->update($workflowData);

        return response()->json([
            'success' => true,
            'message' => 'Workflow updated successfully',
            'data' => $workflow,
        ]);
    }

    /**
     * Remove the specified workflow from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        // Cancel any active executions
        $workflow->executions()->where('status', 'running')->update(['status' => 'cancelled']);

        $workflow->delete();

        return response()->json([
            'success' => true,
            'message' => 'Workflow deleted successfully',
        ]);
    }

    /**
     * Preview which contacts match the workflow trigger conditions.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        // Get sample contacts that would match the workflow trigger
        $sampleSize = min($request->input('sample_size', 10), 100); // Max 100 samples
        $matchingContacts = $workflow->getMatchingContacts($sampleSize);

        // Count total matching contacts
        $totalCount = $workflow->countMatchingContacts();

        return response()->json([
            'success' => true,
            'data' => [
                'workflow' => $workflow,
                'preview_contacts' => $matchingContacts,
                'total_matching_count' => $totalCount,
                'sample_size' => $sampleSize,
            ],
        ]);
    }

    /**
     * Test if a specific contact matches the workflow trigger.
     */
    public function testContact(Request $request, int $workflowId, int $contactId): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($workflowId);
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($contactId);

        $matches = $workflow->matchesContact($contact);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_id' => $workflowId,
                'contact_id' => $contactId,
                'matches' => $matches,
                'contact' => $contact,
                'workflow_graph' => $workflow->graph,
            ],
        ]);
    }

    /**
     * Execute a workflow for a specific contact.
     */
    public function executeForContact(Request $request, int $workflowId, int $contactId): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($workflowId);
        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($contactId);

        // Check if workflow is active
        if (!$workflow->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot execute inactive workflow',
            ], 400);
        }

        // Execute the workflow for the contact
        $execution = $workflow->executeForContact($contact, $request->input('context', []));

        return response()->json([
            'success' => true,
            'message' => 'Workflow execution started successfully',
            'data' => [
                'execution' => $execution,
            ],
        ]);
    }

    /**
     * Get executions for a specific workflow.
     */
    public function getExecutions(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        $query = WorkflowExecution::where('workflow_id', $workflow->id);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        $perPage = min($request->input('per_page', 15), 100); // Max 100 per page
        $executions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $executions,
        ]);
    }

    /**
     * Recalculate contact count for a workflow.
     */
    public function recalculateCount(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        $workflow->recalculateContactCount();

        return response()->json([
            'success' => true,
            'message' => 'Workflow contact count recalculated successfully',
            'data' => [
                'workflow' => $workflow,
                'contact_count' => $workflow->contact_count,
            ],
        ]);
    }

    /**
     * Get workflow statistics.
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;
        
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        $stats = [
            'total_executions' => $workflow->executions()->count(),
            'completed_executions' => $workflow->executions()->completed()->count(),
            'failed_executions' => $workflow->executions()->failed()->count(),
            'active_executions' => $workflow->executions()->running()->count(),
            'waiting_executions' => $workflow->executions()->waiting()->count(),
            'contact_count' => $workflow->contact_count,
            'last_executed_at' => $workflow->last_executed_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}