<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'name_en' => fake()->name(),
            'name_bn' => 'শিক্ষার্থী ' . fake()->numberBetween(1000, 9999),
            'student_code' => 'STU-' . fake()->unique()->numberBetween(10000, 99999),
            'father_name' => fake()->name('male'),
            'mother_name' => fake()->name('female'),
            'date_of_birth' => fake()->date('Y-m-d', '-10 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'religion' => fake()->randomElement(['Islam', 'Christianity', 'Hinduism', 'Buddhism']),
            'nationality' => 'Bangladeshi',
            'status' => 'active',
            'student_phone' => fake()->optional()->phoneNumber(),
            'father_phone' => fake()->phoneNumber(),
            'mother_phone' => fake()->optional()->phoneNumber(),
            'guardian_phone' => fake()->phoneNumber(),
            'present_address' => fake()->address(),
            'permanent_address' => fake()->address(),
        ];
    }

    /**
     * Indicate that the student is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
