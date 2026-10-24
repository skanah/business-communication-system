<?php

namespace App\Traits;

trait TenantScopedModel
{
    /**
     * Boot the trait for a model.
     */
    protected static function bootTenantScopedModel()
    {
        static::addGlobalScope('tenant', function ($builder) {
            // Only apply tenant scope if tenant context is available
            if (app()->bound('current_tenant_id')) {
                $tenantId = app('current_tenant_id');
                $builder->where('tenant_id', $tenantId);
            }
        });
    }

    /**
     * Scope a query to only include records from the current tenant.
     */
    public function scopeForCurrentTenant($query)
    {
        if (app()->bound('current_tenant_id')) {
            $tenantId = app('current_tenant_id');
            return $query->where('tenant_id', $tenantId);
        }
        
        return $query;
    }
}