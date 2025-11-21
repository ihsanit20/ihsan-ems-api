<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'domain' => 'test-school.test',
        ]);

        tenancy()->setTenant($this->tenant);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'Teacher',
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role']
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['X-Tenant-Domain' => $this->tenant->domain])
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $user->id,
                'email' => $user->email,
            ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $loginResponse = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('token');

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/auth/logout');

        $response->assertOk();

        // Token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', explode('|', $token)[1]),
        ]);
    }

    public function test_user_cannot_access_protected_route_without_token(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->getJson('/api/v1/me');

        $response->assertUnauthorized();
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
