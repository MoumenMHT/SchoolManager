<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => 'teacher']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'cin' => fake()->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'birth_date' => fake()->date('Y-m-d', '-30 years'),
            'specialization' => fake()->randomElement(['Mathematics', 'Physics', 'French', 'Arabic', 'English', 'History']),
            'hire_date' => fake()->date('Y-m-d', '-5 years'),
            'salary' => fake()->randomFloat(2, 3000, 8000),
        ];
    }
}
