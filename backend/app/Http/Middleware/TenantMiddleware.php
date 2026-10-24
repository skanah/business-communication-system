<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get tenant from various sources including API key
        $tenantId = $this->resolveTenant($request);

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found or invalid'
            ], 400);
        }

        // Set tenant context for the request
        $request->merge(['tenant_id' => $tenantId]);

        // Store tenant ID in a global context for use in queries
        app()->singleton('current_tenant_id', function () use ($tenantId) {
            return $tenantId;
        });

        return $next($request);
    }

    /**
     * Resolve tenant from various sources
     */
    private function resolveTenant(Request $request): ?string
    {
        // Priority order: API key > header > subdomain > query parameter

        // Check if API key middleware has already set the tenant_id
        $tenantId = $request->get('tenant_id');
        if ($tenantId) {
            return $tenantId;
        }

        // Check header (for direct tenant specification)
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId) {
            return $tenantId;
        }

        // Check subdomain (web scenario)
        $host = $request->getHost();
        if (preg_match('/^([a-zA-Z0-9\-]+)\..*$/', $host, $matches)) {
            $subdomain = $matches[1];
            if ($subdomain !== 'www' && $subdomain !== 'api') {
                return $subdomain;
            }
        }

        // Check query parameter (fallback)
        $tenantId = $request->query('tenant_id');
        if ($tenantId) {
            return $tenantId;
        }

        return null;
    }
}
