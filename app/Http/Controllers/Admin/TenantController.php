<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::query()->latest()->paginate(10)->through(fn($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'domain' => $t->domain,
            'db_name' => $t->db_name,
            'is_active' => $t->is_active,
            'db_host' => $t->db_host,
            'db_port' => $t->db_port,
            'db_username' => $t->db_username,
        ]);

        return Inertia::render('Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'db_name' => 'required|string|max:255|unique:tenants,db_name',
            'is_active' => 'boolean',
            'db_host' => 'nullable|string|max:255',
            'db_port' => 'nullable|integer',
            'db_username' => 'nullable|string|max:255',
            'db_password' => 'nullable|string|max:255',
        ]);
        Tenant::create($data);
        return back()->with('success', 'Tenant created');
    }

    public function update(Request $r, Tenant $tenant)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255',
            'domain' => "required|string|max:255|unique:tenants,domain,{$tenant->id}",
            'db_name' => "required|string|max:255|unique:tenants,db_name,{$tenant->id}",
            'is_active' => 'boolean',
            'db_host' => 'nullable|string|max:255',
            'db_port' => 'nullable|integer',
            'db_username' => 'nullable|string|max:255',
            'db_password' => 'nullable|string|max:255',
        ]);
        $tenant->update($data);
        return back()->with('success', 'Tenant updated');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return back()->with('success', 'Tenant deleted');
    }

    /**
     * Run only tenant migrations and report which ones ran + newly created tables.
     */
    public function migrate(Request $request, Tenant $tenant, TenantManager $tm)
    {
        $tm->setTenant($tenant);

        // Snapshot BEFORE
        $beforeMigs = [];
        $tablesBefore = $this->listTables('tenant');
        try {
            $schema = DB::connection('tenant')->getSchemaBuilder();
            if ($schema->hasTable('migrations')) {
                $beforeMigs = DB::connection('tenant')->table('migrations')->pluck('migration')->all();
            }
        } catch (\Throwable $e) {
            // ignore; might be first run
        }

        // Run migrate
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        // Snapshot AFTER
        $afterMigs = [];
        $tablesAfter = $this->listTables('tenant');
        try {
            $schema = DB::connection('tenant')->getSchemaBuilder();
            if ($schema->hasTable('migrations')) {
                $afterMigs = DB::connection('tenant')->table('migrations')->pluck('migration')->all();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $ran = array_values(array_diff($afterMigs, $beforeMigs));
        $newTables = array_values(array_diff($tablesAfter, $tablesBefore));

        $payload = [
            'ok' => true,
            'ran_migrations' => $ran,     // যে মাইগ্রেশনগুলো এইবার apply হয়েছে
            'new_tables' => $newTables,   // নতুন তৈরি হওয়া টেবিলের নাম (approx)
            'output' => Artisan::output() // Artisan-এর raw আউটপুট
        ];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return back()->with('success', 'Tenant migrations ran (' . count($ran) . ')');
    }

    public function provision(Request $request, Tenant $tenant, TenantManager $tm)
    {
        // (optional) create DB if not exists (central connection)
        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$tenant->db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }

        // now migrate (reuse migrate action so JSON/HTML both work)
        return $this->migrate($request, $tenant, $tm);
    }

    public function status(Tenant $tenant, TenantManager $tm)
    {
        $ok = false;
        $batch = null;
        try {
            $tm->setTenant($tenant);
            $ok = DB::connection('tenant')->select('SELECT 1') ? true : false;
            if (DB::connection('tenant')->getSchemaBuilder()->hasTable('migrations')) {
                $batch = DB::connection('tenant')->table('migrations')->max('batch');
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
        return response()->json(['ok' => $ok, 'last_batch' => $batch]);
    }

    public function toggle(Tenant $tenant)
    {
        $tenant->is_active = ! $tenant->is_active;
        $tenant->save();

        return back()->with('success', 'Tenant ' . ($tenant->is_active ? 'activated' : 'deactivated'));
    }

    public function dbCheck(Tenant $tenant)
    {
        $host = $tenant->db_host ?? env('TENANT_DB_HOST', config('database.connections.mysql.host'));
        $port = $tenant->db_port ?? (int) (env('TENANT_DB_PORT', config('database.connections.mysql.port')) ?: 3306);
        $user = $tenant->db_username ?? env('TENANT_DB_USERNAME', config('database.connections.mysql.username'));
        $pass = $tenant->db_password ?? env('TENANT_DB_PASSWORD', config('database.connections.mysql.password'));
        $db   = $tenant->db_name;

        $res = [
            'ok' => false,
            'connectable' => false,
            'exists' => false,
            'ping_ms' => null,
        ];

        try {
            $t0  = microtime(true);
            $pdo = new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", (string)$user, (string)$pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $res['connectable'] = true;
            $res['ping_ms'] = round((microtime(true) - $t0) * 1000, 1);

            $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([$db]);
            $res['exists'] = (bool) $stmt->fetchColumn();

            $res['ok'] = $res['connectable'];
        } catch (\Throwable $e) {
            $res['error'] = $e->getMessage();
        }

        return response()->json($res);
    }

    public function migrationStatus(Tenant $tenant, TenantManager $tm)
    {
        $tm->setTenant($tenant);

        Artisan::call('migrate:status', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
        ]);

        return response()->json([
            'ok' => true,
            'output' => Artisan::output(),
        ]);
    }

    public function pendingMigrations(Tenant $tenant, TenantManager $tm)
    {
        $tm->setTenant($tenant);

        $files = collect(glob(database_path('migrations/tenant/*.php')))
            ->map(fn($f) => basename($f, '.php'))
            ->values()
            ->all();

        $applied = [];
        $schema = DB::connection('tenant')->getSchemaBuilder();
        if ($schema->hasTable('migrations')) {
            $applied = DB::connection('tenant')->table('migrations')->pluck('migration')->all();
        }

        $pending = array_values(array_diff($files, $applied));

        return response()->json([
            'ok' => true,
            'files_total' => count($files),
            'applied_count' => count($applied),
            'pending_count' => count($pending),
            'pending' => $pending,
        ]);
    }

    /** Helper: list tables for a connection (MySQL) */
    private function listTables(string $connection = 'tenant'): array
    {
        try {
            $conn = DB::connection($connection);
            $dbName = $conn->getDatabaseName();
            if (!$dbName) return [];
            $rows = $conn->select(
                "SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?",
                [$dbName]
            );
            return array_values(array_map(function ($r) {
                return $r->name ?? $r->TABLE_NAME ?? $r->table_name ?? null;
            }, $rows));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
