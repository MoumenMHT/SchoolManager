<?php

namespace Tests\Unit;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_is_properly_recorded(): void
    {
        $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);
        $exam = Exam::factory()->create(['tenant_id' => $this->tenant->id]);

        $grade = Grade::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'grade' => 15.5,
            'comment' => 'Good job',
            'tenant_id' => $this->tenant->id
        ]);

        $this->assertEquals(15.5, $grade->grade);
        $this->assertEquals($student->id, $grade->student->id);
        $this->assertEquals($exam->id, $grade->exam->id);
    }
}
