<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    /**
     * Success response method
     */
    public function sendResponse($result, $message, $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];

        return response()->json($response, $code);
    }

    /**
     * Error response method
     */
    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    /**
     * Get the current tenant from the request
     */
    protected function getCurrentTenant(Request $request): ?Tenant
    {
        $tenantId = $request->get('tenant_id');

        if (!$tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Get the current tenant ID
     */
    protected function getCurrentTenantId(Request $request): ?int
    {
        return $request->get('tenant_id');
    }

    /**
     * Ensure the request has a valid tenant context
     */
    protected function validateTenantContext(Request $request): bool
    {
        $tenantId = $this->getCurrentTenantId($request);
        return $tenantId !== null;
    }

    /**
     * Add tenant scope to query builder
     */
    protected function addTenantScope($query, Request $request)
    {
        $tenantId = $this->getCurrentTenantId($request);
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        return $query;
    }
}
