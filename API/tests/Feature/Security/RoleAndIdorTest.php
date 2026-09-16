<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Grade;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_cannot_view_other_parents_child_report_card(): void
    {
        // 1. Setup the parent and their child
        $parent1 = ParentModel::factory()->create(['tenant_id' => $this->tenant->id]);
        $student1 = Student::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $parent1->id]);
        $parent1User = User::factory()->create(['role' => 'parent', 'tenant_id' => $this->tenant->id]);
        $parent1->update(['user_id' => $parent1User->id]);

        // 2. Setup a second parent and their child
        $parent2 = ParentModel::factory()->create(['tenant_id' => $this->tenant->id]);
        $student2 = Student::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $parent2->id]);
        $parent2User = User::factory()->create(['role' => 'parent', 'tenant_id' => $this->tenant->id]);
        $parent2->update(['user_id' => $parent2User->id]);

        // 3. Parent 1 tries to access their own child's report card
        $response1 = $this->actingAs($parent1User, 'web')
            ->getJson("/api/parent/students/{$student1->id}/report-card?semester=Trimester%201&academic_year=2024-2025");
        $response1->assertStatus(200);

        // 4. Parent 1 tries to access Parent 2's child's report card
        $response2 = $this->actingAs($parent1User, 'web')
            ->getJson("/api/parent/students/{$student2->id}/report-card?semester=Trimester%201&academic_year=2024-2025");
        
        // The policy/controller should block this
        $response2->assertStatus(403);
    }

    public function test_teacher_cannot_submit_grades_for_unassigned_class(): void
    {
        // Setup Teacher 1 and Class 1
        $teacher1 = Teacher::factory()->create(['tenant_id' => $this->tenant->id]);
        $teacher1User = User::factory()->create(['role' => 'teacher', 'tenant_id' => $this->tenant->id]);
        $teacher1->update(['user_id' => $teacher1User->id]);
        $class1 = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        // Setup Teacher 2 and Class 2
        $teacher2 = Teacher::factory()->create(['tenant_id' => $this->tenant->id]);
        $class2 = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id]);
        $exam = Exam::factory()->create(['tenant_id' => $this->tenant->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher2->id, 'exam_type' => 'test', 'semester' => 'S1']);

        $studentInClass2 = Student::factory()->create(['tenant_id' => $this->tenant->id, 'class_id' => $class2->id]);

        // Teacher 1 tries to submit grades for Class 2's exam
        $response = $this->actingAs($teacher1User, 'web')
            ->postJson("/api/grades", [
                'exam_id' => $exam->id,
                'student_id' => $studentInClass2->id,
                'grade' => 15,
                'max_grade' => 20
            ]);

        // Should be forbidden since teacher1 is not assigned to class2
        $response->assertStatus(403);
    }

    public function test_parent_cannot_access_teacher_endpoints(): void
    {
        $parent = ParentModel::factory()->create(['tenant_id' => $this->tenant->id]);
        $parentUser = User::factory()->create(['role' => 'parent', 'tenant_id' => $this->tenant->id]);
        $parent->update(['user_id' => $parentUser->id]);

        // Parents shouldn't be able to list all students
        $response = $this->actingAs($parentUser, 'web')
            ->getJson("/api/students");
        
        $response->assertStatus(403);

        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenant->id]);
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id]);
        $exam = Exam::factory()->create(['tenant_id' => $this->tenant->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'exam_type' => 'test', 'semester' => 'S1']);
        $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);

        // Parents shouldn't be able to submit grades
        $response2 = $this->actingAs($parentUser, 'web')
            ->postJson("/api/grades", [
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'grade' => 15
            ]);
        
        $response2->assertStatus(403);
    }
}
