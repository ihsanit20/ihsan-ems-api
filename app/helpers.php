<?php

/**
 * Tenant Helper Functions
 *
 * Global helper functions for accessing tenant context throughout the application.
 */

if (! function_exists('tenant')) {
    /**
     * Get the current tenant instance.
     *
     * Returns null if no tenant context is active (central domain).
     *
     * @return \App\Models\Tenant|null
     *
     * @example
     * $schoolName = tenant()?->name;
     * if (tenant()) {
     *     // Do tenant-specific work
     * }
     */
    function tenant(): ?\App\Models\Tenant
    {
        return app(\App\Services\Tenancy\TenantManager::class)->tenant();
    }
}

if (! function_exists('tenancy')) {
    /**
     * Get the TenantManager service instance.
     *
     * Use this to access TenantManager methods like setTenant().
     *
     * @return \App\Services\Tenancy\TenantManager
     *
     * @example
     * tenancy()->setTenant($tenant);
     * $currentTenant = tenancy()->tenant();
     */
    function tenancy(): \App\Services\Tenancy\TenantManager
    {
        return app(\App\Services\Tenancy\TenantManager::class);
    }
}
