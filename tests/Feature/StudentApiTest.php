<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tenant\Student;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and set tenant
        $this->tenant = Tenant::factory()->create([
            'domain' => 'test-school.test',
        ]);

        tenancy()->setTenant($this->tenant);

        // Create authenticated user
        $this->user = User::factory()->create([
            'role' => 'Admin',
        ]);
    }

    public function test_can_list_students(): void
    {
        Sanctum::actingAs($this->user);

        Student::factory()->count(5)->create();

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->getJson('/api/v1/students');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name_en', 'name_bn', 'student_code']
                ]
            ]);
    }

    public function test_can_create_student(): void
    {
        Sanctum::actingAs($this->user);

        $studentData = [
            'name_en' => 'John Doe',
            'name_bn' => 'জন ডো',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'date_of_birth' => '2010-01-01',
            'gender' => 'male',
            'blood_group' => 'A+',
            'religion' => 'Islam',
            'status' => 'active',
        ];

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/students', $studentData);

        $response->assertCreated()
            ->assertJsonFragment(['name_en' => 'John Doe']);

        $this->assertDatabaseHas('students', [
            'name_en' => 'John Doe',
        ]);
    }

    public function test_can_view_student_details(): void
    {
        Sanctum::actingAs($this->user);

        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->getJson("/api/v1/students/{$student->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $student->id]);
    }

    public function test_can_update_student(): void
    {
        Sanctum::actingAs($this->user);

        $student = Student::factory()->create([
            'name_en' => 'Old Name',
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->putJson("/api/v1/students/{$student->id}", [
            'name_en' => 'Updated Name',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'name_en' => 'Updated Name',
        ]);
    }

    public function test_can_search_students(): void
    {
        Sanctum::actingAs($this->user);

        Student::factory()->create(['name_en' => 'John Doe']);
        Student::factory()->create(['name_en' => 'Jane Smith']);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->getJson('/api/v1/students?q=John');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name_en' => 'John Doe']);
    }

    public function test_unauthenticated_user_cannot_access_students(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->getJson('/api/v1/students');

        $response->assertUnauthorized();
    }

    public function test_unauthorized_role_cannot_create_student(): void
    {
        $studentUser = User::factory()->create(['role' => 'Student']);
        Sanctum::actingAs($studentUser);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/students', [
            'name_en' => 'Test Student',
        ]);

        $response->assertForbidden();
    }
}
