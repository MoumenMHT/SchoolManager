<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\ParentModel;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test teacher cannot mark attendance for unassigned class.
     */
    public function test_teacher_cannot_mark_attendance_for_unassigned_class(): void
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

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendances', [
                'student_id' => $unassignedStudent->id,
                'subject_id' => $subject->id,
                'status' => 'present',
                'date' => now()->format('Y-m-d')
            ]);

        // Expect 403 Forbidden
        $response->assertStatus(403);
    }

    /**
     * Test teacher cannot bulk store attendance with foreign student.
     */
    public function test_teacher_cannot_bulk_store_attendance_with_foreign_student(): void
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

        // Valid student in class A
        $assignedStudent = Student::factory()->create(['class_id' => $classA->id]);

        // Class B: Teacher does NOT teach this class
        $classB = SchoolClass::factory()->create();
        $unassignedStudent = Student::factory()->create(['class_id' => $classB->id]);

        $this->withoutExceptionHandling();
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendances/bulk', [
                'records' => [
                    [
                        'student_id' => $assignedStudent->id,
                        'subject_id' => $subject->id,
                        'status' => 'present',
                        'date' => now()->format('Y-m-d')
                    ],
                    [
                        'student_id' => $unassignedStudent->id,
                        'subject_id' => $subject->id,
                        'status' => 'absent',
                        'date' => now()->format('Y-m-d')
                    ]
                ]
            ]);

        // Entire transaction should fail due to unauthorized student
        $response->assertStatus(403); // Because we changed it to abort(403) inside bulkStore
        $response->assertJsonFragment(['message' => __('messages.unauthorized')]);

        // Neither attendance should be saved
        $this->assertDatabaseMissing('attendances', [
            'student_id' => $assignedStudent->id
        ]);
        $this->assertDatabaseMissing('attendances', [
            'student_id' => $unassignedStudent->id
        ]);
    }

    /**
     * Test parent cannot view other parents' student attendance.
     */
    public function test_parent_cannot_view_other_parents_student_attendance(): void
    {
        $user = User::factory()->create(['role' => 'parent']);
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $otherParent = ParentModel::factory()->create();
        $otherStudent = Student::factory()->create(['parent_id' => $otherParent->id]);
        
        $attendance = Attendance::factory()->create([
            'student_id' => $otherStudent->id,
            'status' => 'present'
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $otherStudent->id . '/attendances');

        // Expect 403 Forbidden
        $response->assertStatus(403);
    }
}
