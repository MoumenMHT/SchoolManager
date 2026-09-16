<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\ParentModel;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradeSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test teacher cannot modify grades for unassigned student.
     */
    public function test_teacher_cannot_modify_grades_for_unassigned_student(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Class A: Teacher teaches this class
        $classA = SchoolClass::factory()->create();
        $subject = Subject::factory()->create();
        $teacher->classes()->attach($classA->id, [
            'subject_id' => $subject->id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'coefficient' => 1
        ]);

        // Class B: Teacher does NOT teach this class
        $classB = SchoolClass::factory()->create();
        $unassignedStudent = Student::factory()->create(['class_id' => $classB->id]);
        $exam = Exam::factory()->create(['subject_id' => $subject->id]);
        $exam->classes()->attach($classB->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [
                'student_id' => $unassignedStudent->id,
                'exam_id' => $exam->id,
                'grade' => 18,
            ]);

        // Expect 403 Forbidden
        $response->assertStatus(403);

        $this->assertDatabaseMissing('grades', [
            'student_id' => $unassignedStudent->id,
            'exam_id' => $exam->id,
            'grade' => 18,
        ]);
    }

    /**
     * Test parent cannot view other parents' student grades.
     */
    public function test_parent_cannot_view_other_parents_student_grades(): void
    {
        $user = User::factory()->create(['role' => 'parent']);
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $otherParent = ParentModel::factory()->create();
        $otherStudent = Student::factory()->create(['parent_id' => $otherParent->id]);
        
        $exam = Exam::factory()->create();
        $grade = Grade::factory()->create([
            'student_id' => $otherStudent->id,
            'exam_id' => $exam->id,
            'grade' => 15
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $otherStudent->id . '/grades');

        // Expect 403 Forbidden
        $response->assertStatus(403);
    }
}
