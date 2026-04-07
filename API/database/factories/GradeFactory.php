<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'exam_type' => fake()->randomElement(['evaluation_continue', 'devoir', 'composition']),
            'grade' => fake()->randomFloat(2, 0, 20),
            'max_grade' => 20,
            'semester' => fake()->randomElement(['Trimester 1', 'Trimester 2', 'Trimester 3']),
            'academic_year' => '2025-2026',
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
