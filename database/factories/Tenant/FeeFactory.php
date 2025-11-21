<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Fee;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeeFactory extends Factory
{
    protected $model = Fee::class;

    public function definition(): array
    {
        $feeTypes = ['Tuition', 'Admission', 'Library', 'Sports', 'Lab', 'Transport', 'Exam'];

        return [
            'name' => fake()->randomElement($feeTypes) . ' Fee',
            'billing_type' => fake()->randomElement(['one_time', 'recurring']),
            'recurring_cycle' => fake()->randomElement(['monthly', 'yearly', 'term']),
            'amount' => fake()->numberBetween(500, 5000),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
