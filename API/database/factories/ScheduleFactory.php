<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ClassSubjectTeacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $startHour = fake()->numberBetween(8, 15);
        
        return [
            'class_subject_teacher_id' => ClassSubjectTeacher::factory(),
            'day' => fake()->randomElement($days),
            'start_time' => sprintf('%02d:00', $startHour),
            'end_time' => sprintf('%02d:00', $startHour + 1),
            'room' => 'Room ' . fake()->numberBetween(101, 220),
        ];
    }
}
