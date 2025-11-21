<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that tenant data is completely isolated between tenants.
     */
    public function test_tenant_data_is_isolated(): void
    {
        // Create two tenants
        $tenant1 = Tenant::factory()->create([
            'name' => 'School A',
            'domain' => 'school-a.test',
            'db_name' => 'tenant_school_a_test',
        ]);

        $tenant2 = Tenant::factory()->create([
            'name' => 'School B',
            'domain' => 'school-b.test',
            'db_name' => 'tenant_school_b_test',
        ]);

        // Setup tenant1 database
        tenancy()->setTenant($tenant1);
        $studentA = Student::factory()->create(['name_en' => 'Student A']);

        // Switch to tenant2
        tenancy()->setTenant($tenant2);
        $studentB = Student::factory()->create(['name_en' => 'Student B']);

        // Verify isolation
        $this->assertEquals(1, Student::count());
        $this->assertEquals('Student B', Student::first()->name_en);

        // Switch back to tenant1
        tenancy()->setTenant($tenant1);
        $this->assertEquals(1, Student::count());
        $this->assertEquals('Student A', Student::first()->name_en);
    }

    /**
     * Test API request with tenant header works correctly.
     */
    public function test_api_request_with_tenant_header(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'test-school.test',
            'db_name' => 'tenant_test',
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => 'test-school.test',
        ])->getJson('/api/v1/ping');

        $response->assertOk();
    }

    /**
     * Test API request with tenant query parameter.
     */
    public function test_api_request_with_tenant_query(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'test-school.test',
        ]);

        $response = $this->getJson('/api/v1/ping?tenant=test-school.test');

        $response->assertOk();
    }

    /**
     * Test that inactive tenant returns 403.
     */
    public function test_inactive_tenant_returns_forbidden(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'inactive-school.test',
            'is_active' => false,
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => 'inactive-school.test',
        ])->getJson('/api/v1/ping');

        $response->assertStatus(403);
    }

    /**
     * Test that non-existent tenant returns 404.
     */
    public function test_nonexistent_tenant_returns_not_found(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Domain' => 'non-existent.test',
        ])->getJson('/api/v1/ping');

        $response->assertStatus(404);
    }
}
