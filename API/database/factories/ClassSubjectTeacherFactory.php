<?php

namespace Database\Factories;

use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassSubjectTeacherFactory extends Factory
{
    protected $model = ClassSubjectTeacher::class;

    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'academic_year_id' => \App\Models\AcademicYear::factory(),
            'coefficient' => fake()->numberBetween(1, 5),
        ];
    }
}
