<?php

namespace Tests\Unit;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test grade has correct fillable fields
     */
    public function test_grade_has_correct_fillable_fields(): void
    {
        $fillable = ['student_id', 'subject_id', 'teacher_id', 'exam_type', 'grade', 'max_grade', 'semester', 'academic_year', 'comment'];
        $grade = new Grade();
        
        $this->assertEquals($fillable, $grade->getFillable());
    }

    /**
     * Test grade belongs to student
     */
    public function test_grade_belongs_to_student(): void
    {
        $student = Student::factory()->create();
        $grade = Grade::factory()->create(['student_id' => $student->id]);
        
        $this->assertInstanceOf(Student::class, $grade->student);
        $this->assertEquals($student->id, $grade->student->id);
    }

    /**
     * Test grade belongs to subject
     */
    public function test_grade_belongs_to_subject(): void
    {
        $subject = Subject::factory()->create();
        $grade = Grade::factory()->create(['subject_id' => $subject->id]);
        
        $this->assertInstanceOf(Subject::class, $grade->subject);
        $this->assertEquals($subject->id, $grade->subject->id);
    }

    /**
     * Test grade belongs to teacher
     */
    public function test_grade_belongs_to_teacher(): void
    {
        $teacher = Teacher::factory()->create();
        $grade = Grade::factory()->create(['teacher_id' => $teacher->id]);
        
        $this->assertInstanceOf(Teacher::class, $grade->teacher);
        $this->assertEquals($teacher->id, $grade->teacher->id);
    }

    /**
     * Test grade casts numeric fields
     */
    public function test_grade_casts_numeric_fields(): void
    {
        $grade = Grade::factory()->create([
            'grade' => 15.5,
            'max_grade' => 20,
        ]);
        
        $this->assertEquals(15.5, (float)$grade->grade);
        $this->assertEquals(20, (float)$grade->max_grade);
    }
}
