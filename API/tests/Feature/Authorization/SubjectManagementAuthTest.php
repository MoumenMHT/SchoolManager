<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that write operations on subjects are restricted to admin-tier roles.
 * Teachers, parents, supervisors and accountants must receive 403.
 * All authenticated users can still READ subjects (shared route).
 */
class SubjectManagementAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Unauthorized roles cannot create subjects ──────────────────────────────

    /**
     * @dataProvider rolesBlockedFromSubjectWrites
     */
    public function test_role_cannot_create_subject(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/subjects', [
                'name'        => 'Hacked Subject',
                'code'        => 'HACK',
                'description' => 'Should not be created',
            ])
            ->assertStatus(403);
    }

    // ── Unauthorized roles cannot update subjects ──────────────────────────────

    /**
     * @dataProvider rolesBlockedFromSubjectWrites
     */
    public function test_role_cannot_update_subject(string $role): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $subject = Subject::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/subjects/' . $subject->id, [
                'name' => 'Changed',
                'code' => 'CHG',
            ])
            ->assertStatus(403);
    }

    // ── Unauthorized roles cannot delete subjects ──────────────────────────────

    /**
     * @dataProvider rolesBlockedFromSubjectWrites
     */
    public function test_role_cannot_delete_subject(string $role): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $subject = Subject::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/subjects/' . $subject->id)
            ->assertStatus(403);
    }

    // ── All authenticated users CAN read subjects ──────────────────────────────

    /**
     * @dataProvider allRoles
     */
    public function test_all_authenticated_roles_can_list_subjects(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/subjects')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromSubjectWrites(): array
    {
        // /api/subjects CRUD is in route group 2: admin,secretariat,...,accountant
        // accountant IS allowed to write subjects. Only teacher/parent/supervisor blocked:
        return [
            'teacher'    => ['teacher'],
            'parent'     => ['parent'],
            'supervisor' => ['supervisor'],
        ];
    }

    public static function allRoles(): array
    {
        return [
            'admin'            => ['admin'],
            'secretariat'      => ['secretariat'],
            'teacher'          => ['teacher'],
            'parent'           => ['parent'],
            'supervisor'       => ['supervisor'],
            'accountant'       => ['accountant'],
            'primary_director' => ['primary_director'],
        ];
    }
}
