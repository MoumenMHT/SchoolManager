<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExamSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test teacher cannot modify exam for unassigned class.
     */
    public function test_teacher_cannot_modify_exam_for_unassigned_class(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $subject = Subject::factory()->create();

        // Class A: Teacher teaches this class
        $classA = SchoolClass::factory()->create();
        $teacher->classes()->attach($classA->id, [
            'subject_id' => $subject->id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'coefficient' => 1
        ]);

        // Class B: Teacher does NOT teach this class
        $classB = SchoolClass::factory()->create();
        $otherTeacher = Teacher::factory()->create();

        $exam = Exam::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'subject_id' => $subject->id,
            'exam_type' => 'midterm',
        ]);
        $exam->classes()->attach($classB->id);

        // Attempt to update the exam (e.g., change its type)
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/exams/' . $exam->id, [
                'exam_type' => 'final',
            ]);

        // Should be forbidden because this teacher doesn't own this exam
        $response->assertStatus(403);
    }
}
