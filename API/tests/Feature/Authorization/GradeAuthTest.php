<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that grade management routes enforce correct authorization.
 * Only teacher/supervisor/admin-tier roles can access grade routes.
 * Parents and accountants must be blocked entirely.
 */
class GradeAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Roles blocked at the route level ───────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromGrades
     */
    public function test_role_cannot_create_grade(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', ['grade' => 15])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromGrades
     */
    public function test_role_cannot_list_grades(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/grades')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromGrades
     */
    public function test_role_cannot_delete_grade(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;
        $grade = Grade::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/grades/' . $grade->id)
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromGrades
     */
    public function test_role_cannot_bulk_store_grades(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades/bulk', ['grades' => []])
            ->assertStatus(403);
    }

    // ── Teacher business-logic restriction: cannot grade unassigned class ──────

    public function test_teacher_cannot_grade_student_in_unassigned_class(): void
    {
        // Teacher assigned to classA
        $user    = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $token   = $user->createToken('test')->plainTextToken;

        $academicYear = AcademicYear::factory()->create();
        $classA       = SchoolClass::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject      = Subject::factory()->create();

        $teacher->classes()->attach($classA->id, [
            'subject_id'       => $subject->id,
            'academic_year_id' => $academicYear->id,
            'coefficient'      => 1,
        ]);

        // Student in classB — teacher is NOT assigned to classB
        $classB   = SchoolClass::factory()->create();
        $student  = Student::factory()->create(['class_id' => $classB->id]);
        $exam     = Exam::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [
                'student_id' => $student->id,
                'exam_id'    => $exam->id,
                'grade'      => 14,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('grades', [
            'student_id' => $student->id,
            'exam_id'    => $exam->id,
        ]);
    }

    // ── Parent cannot access student grades via /api/students (not parent portal) ──

    public function test_parent_cannot_access_student_grades_via_admin_route(): void
    {
        $user    = User::factory()->create(['role' => 'parent']);
        $token   = $user->createToken('test')->plainTextToken;
        $student = Student::factory()->create();

        // Admin route: /api/students/{id}/grades — parent is NOT allowed here
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $student->id . '/grades')
            ->assertStatus(403);
    }

    // ── Admin can CRUD grades ──────────────────────────────────────────────────

    public function test_admin_can_list_grades(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/grades')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromGrades(): array
    {
        return [
            'parent'     => ['parent'],
            'accountant' => ['accountant'],
        ];
    }
}
