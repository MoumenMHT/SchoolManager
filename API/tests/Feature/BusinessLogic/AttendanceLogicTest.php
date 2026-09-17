<?php

namespace Tests\Feature\BusinessLogic;

use Tests\TestCase;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\ClassSubjectTeacher;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceLogicTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $teacherUser;
    protected $teacher;
    protected $student;
    protected $class;
    protected $subject;
    protected $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacherUser = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);

        $this->class = SchoolClass::factory()->create();
        $this->subject = Subject::factory()->create();
        $this->student = Student::factory()->create(['class_id' => $this->class->id]);

        $assignment = ClassSubjectTeacher::create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'coefficient' => 1,
        ]);

        $this->schedule = Schedule::factory()->create([
            'class_subject_teacher_id' => $assignment->id,
        ]);
    }

    public function test_prevent_duplicate_attendance_records()
    {
        $this->actingAs($this->teacherUser);

        $date = now()->format('Y-m-d');

        // First attendance record
        $response1 = $this->postJson("/api/attendances", [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'schedule_id' => $this->schedule->id,
            'date' => $date,
            'status' => 'present',
        ]);
        $response1->assertStatus(201);
        $this->assertDatabaseCount('attendances', 1);

        // Second attendance record for same student, subject, schedule, date
        $response2 = $this->postJson("/api/attendances", [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'schedule_id' => $this->schedule->id,
            'date' => $date,
            'status' => 'absent',
            'reason' => 'Sick',
        ]);
        $response2->assertStatus(201);

        $this->assertDatabaseCount('attendances', 1);
        $this->assertEquals('absent', Attendance::first()->status);
        $this->assertEquals('Sick', Attendance::first()->reason);
    }
}
