<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies attendance management authorization.
 * Parents and accountants must be blocked at the route level.
 * Teachers may only record attendance for their assigned classes.
 */
class AttendanceAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Roles blocked at the route level ───────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromAttendance
     */
    public function test_role_cannot_create_attendance(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendances', [])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromAttendance
     */
    public function test_role_cannot_list_attendances(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/attendances')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromAttendance
     */
    public function test_role_cannot_delete_attendance(string $role): void
    {
        $user       = User::factory()->create(['role' => $role]);
        $token      = $user->createToken('test')->plainTextToken;
        $attendance = Attendance::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/attendances/' . $attendance->id)
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromAttendance
     */
    public function test_role_cannot_bulk_store_attendance(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendances/bulk', ['records' => []])
            ->assertStatus(403);
    }

    // ── Teacher business-logic restriction ─────────────────────────────────────

    public function test_teacher_cannot_record_attendance_for_unassigned_class(): void
    {
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

        // Student in classB — teacher does NOT teach classB
        $classB  = SchoolClass::factory()->create();
        $student = Student::factory()->create(['class_id' => $classB->id]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendances', [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'status'     => 'present',
                'date'       => now()->format('Y-m-d'),
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
    }

    // ── Parent cannot access student attendance via admin route ────────────────

    public function test_parent_cannot_view_student_attendances_via_admin_route(): void
    {
        $user    = User::factory()->create(['role' => 'parent']);
        $token   = $user->createToken('test')->plainTextToken;
        $student = Student::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $student->id . '/attendances')
            ->assertStatus(403);
    }

    // ── Admin can list attendances ─────────────────────────────────────────────

    public function test_admin_can_list_attendances(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/attendances')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromAttendance(): array
    {
        return [
            'parent'     => ['parent'],
            'accountant' => ['accountant'],
        ];
    }
}
