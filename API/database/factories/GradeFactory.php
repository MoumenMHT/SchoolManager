<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'exam_id'    => Exam::factory(),
            'grade'      => fake()->randomFloat(2, 0, 20),
            'comment'    => fake()->optional()->sentence(),
        ];
    }
}
