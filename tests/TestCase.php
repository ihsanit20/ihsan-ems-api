<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected ?Tenant $testTenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for central database
        $this->artisan('migrate:fresh');

        // Create a test tenant
        $this->testTenant = Tenant::create([
            'name' => 'Test School',
            'domain' => 'test-school.test',
            'database' => 'ihsan_ems_api_testing_tenant',
            'is_active' => true,
        ]);

        // Create tenant database and run migrations
        DB::statement("CREATE DATABASE IF NOT EXISTS {$this->testTenant->database}");

        config(['database.connections.tenant.database' => $this->testTenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->testTenant) {
            DB::statement("DROP DATABASE IF EXISTS {$this->testTenant->database}");
        }

        parent::tearDown();
    }

    /**
     * Create an authenticated user for testing
     */
    protected function createAuthUser(string $role = 'Admin'): User
    {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    /**
     * Make an API request with tenant header
     */
    protected function withTenant(string $domain = null)
    {
        return $this->withHeader('X-Tenant-Domain', $domain ?? $this->testTenant->domain);
    }
}
