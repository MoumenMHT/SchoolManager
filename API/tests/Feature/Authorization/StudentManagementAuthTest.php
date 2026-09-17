<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that only admin-tier roles can create, update and delete students.
 * Teachers, parents and supervisors must receive 403 Forbidden on write operations.
 */
class StudentManagementAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Write operations blocked for low-privilege roles ───────────────────────

    /**
     * @dataProvider rolesBlockedFromStudentWrites
     */
    public function test_role_cannot_create_student(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/students', [
                'first_name'   => 'Test',
                'last_name'    => 'Student',
                'birth_date'   => '2010-01-01',
                'gender'       => 'M',
                'national_id'  => '123456789',
            ])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromStudentWrites
     */
    public function test_role_cannot_update_student(string $role): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $student = Student::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/students/' . $student->id, ['first_name' => 'Changed'])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromStudentWrites
     */
    public function test_role_cannot_delete_student(string $role): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $student = Student::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/students/' . $student->id)
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromStudentList
     */
    public function test_role_cannot_list_all_students(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromStudentList
     */
    public function test_role_cannot_view_students_without_class(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/without-class')
            ->assertStatus(403);
    }

    // ── Admin-tier roles CAN access ────────────────────────────────────────────

    public function test_admin_can_list_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students')
            ->assertStatus(200);
    }

    public function test_secretariat_can_list_students(): void
    {
        $user  = User::factory()->create(['role' => 'secretariat']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromStudentWrites(): array
    {
        // Route group: admin,secretariat,primary_director,cem_director,lycee_director,accountant
        // Roles NOT in that list are blocked:
        return [
            'teacher'    => ['teacher'],
            'parent'     => ['parent'],
            'supervisor' => ['supervisor'],
        ];
    }

    public static function rolesBlockedFromStudentList(): array
    {
        // Teachers can list via their own route (teacher/students).
        // Parents access students via the parent portal (/parent/students).
        // Here we test the admin-facing /api/students index:
        return [
            'parent'     => ['parent'],
            'supervisor' => ['supervisor'],
        ];
    }
}
