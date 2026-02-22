<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'date' => fake()->date(),
            'status' => fake()->randomElement(['present', 'absent', 'late', 'excused']),
            'time' => fake()->time('H:i'),
            'reason' => fake()->optional()->sentence(),
        ];
    }
}
