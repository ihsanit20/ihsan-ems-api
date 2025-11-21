<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Grade;
use App\Models\Tenant\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        $gradeNames = [
            'Class 1',
            'Class 2',
            'Class 3',
            'Class 4',
            'Class 5',
            'Class 6',
            'Class 7',
            'Class 8',
            'Class 9',
            'Class 10'
        ];

        return [
            'level_id' => Level::factory(),
            'name' => fake()->randomElement($gradeNames),
            'code' => 'GR-' . fake()->unique()->numberBetween(1, 100),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
