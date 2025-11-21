<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

class LevelFactory extends Factory
{
    protected $model = Level::class;

    public function definition(): array
    {
        $levels = ['Primary', 'Secondary', 'Higher Secondary'];

        return [
            'name' => fake()->randomElement($levels),
            'code' => 'LVL-' . fake()->unique()->numberBetween(1, 100),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
