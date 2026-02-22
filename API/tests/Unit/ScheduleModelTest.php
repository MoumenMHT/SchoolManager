<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Models\ClassSubjectTeacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test schedule has correct fillable fields
     */
    public function test_schedule_has_correct_fillable_fields(): void
    {
        $fillable = ['class_subject_teacher_id', 'day', 'start_time', 'end_time', 'room'];
        $schedule = new Schedule();
        
        $this->assertEquals($fillable, $schedule->getFillable());
    }

    /**
     * Test schedule belongs to assignment
     */
    public function test_schedule_belongs_to_assignment(): void
    {
        $assignment = ClassSubjectTeacher::factory()->create();
        $schedule = Schedule::factory()->create([
            'class_subject_teacher_id' => $assignment->id,
        ]);
        
        $this->assertInstanceOf(ClassSubjectTeacher::class, $schedule->assignment);
        $this->assertEquals($assignment->id, $schedule->assignment->id);
    }

    /**
     * Test schedule day values
     */
    public function test_schedule_day_values(): void
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        foreach ($days as $day) {
            $schedule = Schedule::factory()->create(['day' => $day]);
            $this->assertEquals($day, $schedule->day);
        }
    }

    /**
     * Test schedule can be created
     */
    public function test_schedule_can_be_created(): void
    {
        $schedule = Schedule::factory()->create([
            'day' => 'Monday',
            'room' => 'Room 101',
        ]);
        
        $this->assertDatabaseHas('schedules', [
            'day' => 'Monday',
            'room' => 'Room 101',
        ]);
    }
}
