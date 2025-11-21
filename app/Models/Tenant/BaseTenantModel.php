<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model for all tenant-specific models.
 *
 * This abstract class ensures all tenant models use the 'tenant' database connection
 * and provides a centralized place for common tenant model functionality.
 *
 * @package App\Models\Tenant
 */
abstract class BaseTenantModel extends Model
{
    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * Boot the model.
     *
     * This method can be overridden in child classes to add custom boot logic.
     * Always call parent::boot() when overriding.
     */
    protected static function boot()
    {
        parent::boot();

        // Future: Add common tenant model behaviors here
        // Examples:
        // - Automatic tenant isolation
        // - Audit logging (created_by, updated_by)
        // - Soft delete tracking
        // - Global scopes for tenant safety
    }
}
