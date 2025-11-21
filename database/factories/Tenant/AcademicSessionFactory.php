<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicSessionFactory extends Factory
{
    protected $model = AcademicSession::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2020, 2025);

        return [
            'name' => "Academic Year {$year}",
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }
}
