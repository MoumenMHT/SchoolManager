<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'subject_id'    => Subject::factory(),
            'teacher_id'    => Teacher::factory(),
            'exam_type'     => fake()->randomElement(['evaluation_continue', 'devoir_1', 'devoir_2', 'composition']),
            'semester'      => fake()->randomElement(['Trimester 1', 'Trimester 2', 'Trimester 3']),
            'academic_year' => '2025-2026',
            'max_grade'     => 20,
        ];
    }
}
