<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\StudentFee;
use App\Models\Tenant\Student;
use App\Models\Tenant\SessionFee;
use App\Models\Tenant\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFeeFactory extends Factory
{
    protected $model = StudentFee::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'session_fee_id' => SessionFee::factory(),
            'amount' => fake()->numberBetween(500, 5000),
            'discount_type' => null,
            'discount_value' => null,
        ];
    }

    public function withFlatDiscount(int $amount = 100): static
    {
        return $this->state(fn(array $attributes) => [
            'discount_type' => 'flat',
            'discount_value' => $amount,
        ]);
    }

    public function withPercentDiscount(int $percent = 10): static
    {
        return $this->state(fn(array $attributes) => [
            'discount_type' => 'percent',
            'discount_value' => $percent,
        ]);
    }
}
