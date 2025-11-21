<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Fee;
use App\Models\Tenant\Grade;
use App\Models\Tenant\SessionFee;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentFee;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Student $student;
    private AcademicSession $session;
    private SessionFee $sessionFee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['domain' => 'test.local']);
        tenancy()->setTenant($this->tenant);

        $this->user = User::factory()->create(['role' => 'Admin']);
        $this->student = Student::factory()->create();
        $this->session = AcademicSession::factory()->create();

        $fee = Fee::factory()->create(['amount' => 1000]);
        $grade = Grade::factory()->create();

        $this->sessionFee = SessionFee::factory()->create([
            'academic_session_id' => $this->session->id,
            'grade_id' => $grade->id,
            'fee_id' => $fee->id,
            'amount' => 1000,
        ]);
    }

    public function test_can_assign_fee_to_student(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/student-fees', [
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'session_fee_id' => $this->sessionFee->id,
            'amount' => 1000,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('student_fees', [
            'student_id' => $this->student->id,
            'session_fee_id' => $this->sessionFee->id,
            'amount' => 1000,
        ]);
    }

    public function test_can_apply_discount_to_student_fee(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/student-fees', [
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'session_fee_id' => $this->sessionFee->id,
            'amount' => 1000,
            'discount_type' => 'flat',
            'discount_value' => 200,
        ]);

        $response->assertCreated();

        $studentFee = StudentFee::first();
        $this->assertEquals(200, $studentFee->discount_value);
        $this->assertEquals('flat', $studentFee->discount_type);
    }

    public function test_can_assign_multiple_fees_to_student(): void
    {
        Sanctum::actingAs($this->user);

        $fee2 = Fee::factory()->create(['amount' => 500]);
        $sessionFee2 = SessionFee::factory()->create([
            'academic_session_id' => $this->session->id,
            'fee_id' => $fee2->id,
            'amount' => 500,
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->postJson('/api/v1/student-fees', [
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'items' => [
                ['session_fee_id' => $this->sessionFee->id, 'amount' => 1000],
                ['session_fee_id' => $sessionFee2->id, 'amount' => 500],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(2, StudentFee::count());
    }

    public function test_can_list_student_fees(): void
    {
        Sanctum::actingAs($this->user);

        StudentFee::factory()->count(3)->create([
            'student_id' => $this->student->id,
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->getJson('/api/v1/student-fees?student_id=' . $this->student->id);

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_update_student_fee_amount(): void
    {
        Sanctum::actingAs($this->user);

        $studentFee = StudentFee::factory()->create([
            'student_id' => $this->student->id,
            'amount' => 1000,
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Domain' => $this->tenant->domain,
        ])->putJson("/api/v1/student-fees/{$studentFee->id}", [
            'amount' => 1200,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('student_fees', [
            'id' => $studentFee->id,
            'amount' => 1200,
        ]);
    }

    public function test_fee_calculation_with_flat_discount(): void
    {
        $studentFee = StudentFee::factory()->create([
            'amount' => 1000,
            'discount_type' => 'flat',
            'discount_value' => 200,
        ]);

        $this->assertEquals(800, $studentFee->payable_amount);
    }

    public function test_fee_calculation_with_percent_discount(): void
    {
        $studentFee = StudentFee::factory()->create([
            'amount' => 1000,
            'discount_type' => 'percent',
            'discount_value' => 20, // 20%
        ]);

        $this->assertEquals(800, $studentFee->payable_amount);
    }
}
