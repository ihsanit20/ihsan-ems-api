<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\SessionFee;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Grade;
use App\Models\Tenant\Fee;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionFeeFactory extends Factory
{
    protected $model = SessionFee::class;

    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'grade_id' => Grade::factory(),
            'fee_id' => Fee::factory(),
            'amount' => fake()->numberBetween(500, 5000),
        ];
    }
}
