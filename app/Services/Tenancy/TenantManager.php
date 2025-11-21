<?php

// app/Services/Tenancy/TenantManager.php
namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantManager
{
    protected ?Tenant $tenant = null;

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;

        DB::purge('tenant');

        $host     = $tenant->db_host     ?? env('TENANT_DB_HOST', config('database.connections.mysql.host'));
        $port     = $tenant->db_port     ?? env('TENANT_DB_PORT', config('database.connections.mysql.port'));
        $database = $tenant->db_name; // required
        $username = $tenant->db_username ?? env('TENANT_DB_USERNAME', config('database.connections.mysql.username'));
        $password = $tenant->db_password ?? env('TENANT_DB_PASSWORD', config('database.connections.mysql.password'));

        Config::set('database.connections.tenant', [
            'driver'   => 'mysql',
            'host'     => $host,
            'port'     => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'   => '',
            'strict'   => true,
        ]);

        DB::reconnect('tenant');

        // Add tenant context to all logs for easier debugging
        Log::withContext([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_domain' => $tenant->domain,
        ]);
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }
}
