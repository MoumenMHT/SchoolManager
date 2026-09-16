<?php

namespace Tests\Unit;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test grade has correct fillable fields (new exam-based architecture)
     */
    public function test_grade_has_correct_fillable_fields(): void
    {
        $fillable = ['student_id', 'exam_id', 'grade', 'comment'];
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
     * Test grade belongs to exam (new architecture)
     */
    public function test_grade_belongs_to_exam(): void
    {
        $exam = Exam::factory()->create();
        $grade = Grade::factory()->create(['exam_id' => $exam->id]);

        $this->assertInstanceOf(Exam::class, $grade->exam);
        $this->assertEquals($exam->id, $grade->exam->id);
    }

    /**
     * Test grade casts numeric field
     */
    public function test_grade_casts_numeric_fields(): void
    {
        $grade = Grade::factory()->create(['grade' => 15.5]);

        $this->assertEquals(15.5, (float) $grade->grade);
    }

    /**
     * Test grade can access subject through exam relationship
     */
    public function test_grade_can_access_subject_via_exam(): void
    {
        $grade = Grade::factory()->create();
        $grade->load('exam.subject');

        $this->assertNotNull($grade->exam);
        $this->assertNotNull($grade->exam->subject);
    }
}
