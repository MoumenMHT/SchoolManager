<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\ParentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'code' => 'STU' . fake()->unique()->numberBetween(1000, 9999),
            'birth_date' => fake()->date('Y-m-d', '-10 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'parent_id' => ParentModel::factory(),
            'class_id' => SchoolClass::factory(),
            'enrollment_date' => fake()->date('Y-m-d', '-2 years'),
            'medical_info' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
