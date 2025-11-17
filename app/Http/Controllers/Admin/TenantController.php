<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

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
            'branding_urls' => $t->branding_urls,
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
     * ✅ Upload branding assets for a tenant:
     * - Accepts 1 PNG file (logo)
     * - Generates two objects on S3 (versioned path):
     *   - logo.png      (pre-sized for UI/header)
     *   - favicon.png   (32x32 universal favicon)
     * - Updates central tenants.branding JSON with S3 object keys
     */
    public function uploadBranding(Request $request, Tenant $tenant)
    {
        $request->validate([
            // PNG-only to avoid Imagick/SVG rasterizer requirements
            'logo' => ['required', 'file', 'mimetypes:image/png', 'max:3072'], // 3MB
            // Optional overrides (pixels). Safe defaults will be used otherwise.
            'logo_width'   => ['nullable', 'integer', 'min:64', 'max:1024'],
            'favicon_size' => ['nullable', 'integer', 'in:16,24,32,48'], // choose one, default 32
        ]);

        $logoWidth   = (int) ($request->input('logo_width') ?: 240);
        $faviconSize = (int) ($request->input('favicon_size') ?: 32);

        $disk = Storage::disk('s3');
        $version = now()->format('YmdHis') . '-' . substr((string) Str::uuid(), 0, 8);
        $base = "tenants/{$tenant->id}/branding/{$version}/";

        $manager = new ImageManager(new Driver());

        try {
            $img = $manager->read($request->file('logo')->getRealPath());

            // 1) Pre-sized logo (keep aspect, limit width)
            $logo = (clone $img)->scaleDown($logoWidth, $logoWidth * 10);
            $disk->put($base . 'logo.png', (string) $logo->toPng(), [
                'visibility'   => 'public',
                'ContentType'  => 'image/png',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            // 2) Universal favicon (square crop)
            $favicon = (clone $img)->cover($faviconSize, $faviconSize);
            $disk->put($base . 'favicon.png', (string) $favicon->toPng(), [
                'visibility'   => 'public',
                'ContentType'  => 'image/png',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            // Update central branding JSON with object keys (not URLs)
            $branding = array_merge($tenant->branding ?? [], [
                'version'     => $version,
                'logo_key'    => $base . 'logo.png',
                'favicon_key' => $base . 'favicon.png',
            ]);

            $tenant->update(['branding' => $branding]);

            $payload = [
                'ok'            => true,
                'branding'      => $tenant->branding,
                'branding_urls' => $tenant->branding_urls, // resolved by model accessor
            ];

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json($payload);
            }
            return back()->with('success', 'Branding uploaded');
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
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

    /**
     * Run any tenant seeder
     */
    public function seed(Request $request, Tenant $tenant, TenantManager $tm)
    {
        $request->validate([
            'seeder' => 'required|string',
        ]);

        $seederClass = $request->input('seeder');

        // Security: ensure it's a valid tenant seeder class
        if (!str_starts_with($seederClass, 'Database\\Seeders\\Tenant\\')) {
            return response()->json(['ok' => false, 'error' => 'Invalid seeder class'], 400);
        }

        $tm->setTenant($tenant);

        // Get all tables BEFORE
        $tablesBefore = $this->listTables('tenant');
        $beforeCounts = [];
        foreach ($tablesBefore as $table) {
            try {
                $beforeCounts[$table] = DB::connection('tenant')->table($table)->count();
            } catch (\Throwable $e) {
                // skip
            }
        }

        // Run seeder
        try {
            Artisan::call('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
            ]);
        } catch (\Throwable $e) {
            $payload = [
                'ok' => false,
                'error' => $e->getMessage(),
                'output' => Artisan::output()
            ];

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json($payload, 500);
            }
            return back()->with('error', 'Seeding failed: ' . $e->getMessage());
        }

        // Get all tables AFTER
        $tablesAfter = $this->listTables('tenant');
        $afterCounts = [];
        $inserted = [];
        foreach ($tablesAfter as $table) {
            try {
                $afterCounts[$table] = DB::connection('tenant')->table($table)->count();
                $diff = $afterCounts[$table] - ($beforeCounts[$table] ?? 0);
                if ($diff > 0) {
                    $inserted[$table] = $diff;
                }
            } catch (\Throwable $e) {
                // skip
            }
        }

        $totalInserted = array_sum($inserted);

        $payload = [
            'ok' => true,
            'seeder' => $seederClass,
            'inserted' => $inserted,
            'total_inserted' => $totalInserted,
            'output' => Artisan::output()
        ];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return back()->with('success', "Seeder ran successfully! Inserted {$totalInserted} records.");
    }

    /**
     * List available tenant seeders
     */
    public function availableSeeders()
    {
        $path = database_path('seeders/tenant');
        $seeders = [];

        if (is_dir($path)) {
            $files = glob($path . '/*.php');
            foreach ($files as $file) {
                $basename = basename($file, '.php');
                $seeders[] = [
                    'name' => $basename,
                    'class' => "Database\\Seeders\\Tenant\\{$basename}",
                ];
            }
        }

        return response()->json(['ok' => true, 'seeders' => $seeders]);
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
