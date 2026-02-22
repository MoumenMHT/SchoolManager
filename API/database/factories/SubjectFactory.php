<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Mathematics', 'Physics', 'French', 'Arabic', 'English', 'History', 'Geography']),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'description' => fake()->sentence(),
        ];
    }
}
