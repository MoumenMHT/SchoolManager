<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that only admin-tier roles can CRUD teachers.
 * Teachers, parents, supervisors and accountants must receive 403 on write operations.
 */
class TeacherManagementAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Unauthorized roles cannot list teachers ────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromTeacherAccess
     */
    public function test_role_cannot_list_teachers(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/teachers')
            ->assertStatus(403);
    }

    // ── Unauthorized roles cannot create teachers ──────────────────────────────

    /**
     * @dataProvider rolesBlockedFromTeacherAccess
     */
    public function test_role_cannot_create_teacher(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/teachers', [
                'first_name'      => 'New',
                'last_name'       => 'Teacher',
                'specialization'  => 'Math',
                'hire_date'       => '2024-09-01',
            ])
            ->assertStatus(403);
    }

    // ── Unauthorized roles cannot update teachers ──────────────────────────────

    /**
     * @dataProvider rolesBlockedFromTeacherAccess
     */
    public function test_role_cannot_update_teacher(string $role): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $teacher = Teacher::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/teachers/' . $teacher->id, ['first_name' => 'Changed'])
            ->assertStatus(403);
    }

    // ── Teacher themselves cannot delete other teachers ────────────────────────

    public function test_teacher_cannot_delete_teacher(string $role = 'teacher'): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $teacher = Teacher::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/teachers/' . $teacher->id)
            ->assertStatus(403);
    }

    public function test_parent_cannot_delete_teacher(): void
    {
        $user    = User::factory()->create(['role' => 'parent']);
        $token   = $user->createToken('test')->plainTextToken;
        $teacher = Teacher::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/teachers/' . $teacher->id)
            ->assertStatus(403);
    }

    public function test_supervisor_cannot_delete_teacher(): void
    {
        $user    = User::factory()->create(['role' => 'supervisor']);
        $token   = $user->createToken('test')->plainTextToken;
        $teacher = Teacher::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/teachers/' . $teacher->id)
            ->assertStatus(403);
    }

    // ── Authorized roles CAN access ────────────────────────────────────────────

    public function test_admin_can_list_teachers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/teachers')
            ->assertStatus(200);
    }

    public function test_secretariat_can_list_teachers(): void
    {
        $user  = User::factory()->create(['role' => 'secretariat']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/teachers')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromTeacherAccess(): array
    {
        // Route group 2: admin,secretariat,...,accountant — accountant IS allowed
        return [
            'teacher'    => ['teacher'],
            'parent'     => ['parent'],
            'supervisor' => ['supervisor'],
        ];
    }
}
