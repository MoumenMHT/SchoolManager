<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test attendance has correct fillable fields
     */
    public function test_attendance_has_correct_fillable_fields(): void
    {
        $fillable = ['student_id', 'subject_id', 'teacher_id', 'date', 'status', 'time', 'reason'];
        $attendance = new Attendance();
        
        $this->assertEquals($fillable, $attendance->getFillable());
    }

    /**
     * Test attendance belongs to student
     */
    public function test_attendance_belongs_to_student(): void
    {
        $student = Student::factory()->create();
        $attendance = Attendance::factory()->create(['student_id' => $student->id]);
        
        $this->assertInstanceOf(Student::class, $attendance->student);
        $this->assertEquals($student->id, $attendance->student->id);
    }

    /**
     * Test attendance belongs to subject
     */
    public function test_attendance_belongs_to_subject(): void
    {
        $subject = Subject::factory()->create();
        $attendance = Attendance::factory()->create(['subject_id' => $subject->id]);
        
        $this->assertInstanceOf(Subject::class, $attendance->subject);
        $this->assertEquals($subject->id, $attendance->subject->id);
    }

    /**
     * Test attendance belongs to teacher
     */
    public function test_attendance_belongs_to_teacher(): void
    {
        $teacher = Teacher::factory()->create();
        $attendance = Attendance::factory()->create(['teacher_id' => $teacher->id]);
        
        $this->assertInstanceOf(Teacher::class, $attendance->teacher);
        $this->assertEquals($teacher->id, $attendance->teacher->id);
    }

    /**
     * Test attendance casts date field
     */
    public function test_attendance_casts_date_field(): void
    {
        $attendance = Attendance::factory()->create([
            'date' => '2026-02-19',
        ]);
        
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $attendance->date);
    }

    /**
     * Test attendance status values
     */
    public function test_attendance_status_values(): void
    {
        $present = Attendance::factory()->create(['status' => 'present']);
        $absent = Attendance::factory()->create(['status' => 'absent']);
        $late = Attendance::factory()->create(['status' => 'late']);
        
        $this->assertEquals('present', $present->status);
        $this->assertEquals('absent', $absent->status);
        $this->assertEquals('late', $late->status);
    }
}
