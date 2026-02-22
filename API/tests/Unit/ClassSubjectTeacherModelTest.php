<?php

namespace Tests\Unit;

use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSubjectTeacherModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test assignment has correct fillable fields
     */
    public function test_assignment_has_correct_fillable_fields(): void
    {
        $fillable = ['class_id', 'subject_id', 'teacher_id', 'academic_year', 'coefficient'];
        $assignment = new ClassSubjectTeacher();
        
        $this->assertEquals($fillable, $assignment->getFillable());
    }

    /**
     * Test assignment belongs to class
     */
    public function test_assignment_belongs_to_class(): void
    {
        $class = SchoolClass::factory()->create();
        $assignment = ClassSubjectTeacher::factory()->create(['class_id' => $class->id]);
        
        $this->assertInstanceOf(SchoolClass::class, $assignment->class);
        $this->assertEquals($class->id, $assignment->class->id);
    }

    /**
     * Test assignment belongs to subject
     */
    public function test_assignment_belongs_to_subject(): void
    {
        $subject = Subject::factory()->create();
        $assignment = ClassSubjectTeacher::factory()->create(['subject_id' => $subject->id]);
        
        $this->assertInstanceOf(Subject::class, $assignment->subject);
        $this->assertEquals($subject->id, $assignment->subject->id);
    }

    /**
     * Test assignment belongs to teacher
     */
    public function test_assignment_belongs_to_teacher(): void
    {
        $teacher = Teacher::factory()->create();
        $assignment = ClassSubjectTeacher::factory()->create(['teacher_id' => $teacher->id]);
        
        $this->assertInstanceOf(Teacher::class, $assignment->teacher);
        $this->assertEquals($teacher->id, $assignment->teacher->id);
    }

    /**
     * Test assignment has many schedules
     */
    public function test_assignment_has_many_schedules(): void
    {
        $assignment = ClassSubjectTeacher::factory()->create();
        Schedule::factory()->count(5)->create(['class_subject_teacher_id' => $assignment->id]);
        
        $this->assertCount(5, $assignment->schedules);
    }

    /**
     * Test assignment casts coefficient to integer
     */
    public function test_assignment_casts_coefficient_to_integer(): void
    {
        $assignment = ClassSubjectTeacher::factory()->create(['coefficient' => 3]);
        
        $this->assertIsInt($assignment->coefficient);
        $this->assertEquals(3, $assignment->coefficient);
    }
}
