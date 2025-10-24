<?php

namespace App\Models;

use App\Services\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Central vs Tenant — কোন কানেকশন ব্যবহার হবে তা runtime-এ ঠিক করি।
     * Tenant context থাকলে 'tenant' কানেকশন, নইলে default (mysql/sqlite).
     */
    public function getConnectionName()
    {
        try {
            /** @var TenantManager $tm */
            $tm = app(TenantManager::class);
            if (method_exists($tm, 'tenant') && $tm->tenant()) {
                return 'tenant';
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }
        return config('database.default'); // e.g. 'mysql' or 'sqlite'
    }
}
