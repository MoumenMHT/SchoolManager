<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that only admin-tier roles can CRUD classes.
 * Teachers, parents, supervisors and accountants must receive 403 on write operations.
 */
class ClassManagementAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Unauthorized roles cannot create classes ───────────────────────────────

    /**
     * @dataProvider rolesBlockedFromClassWrites
     */
    public function test_role_cannot_create_class(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/classes', [
                'name'     => '6ème A',
                'level'    => '6ème',
                'capacity' => 30,
            ])
            ->assertStatus(403);
    }

    // ── Unauthorized roles cannot update classes ───────────────────────────────

    /**
     * @dataProvider rolesBlockedFromClassWrites
     */
    public function test_role_cannot_update_class(string $role): void
    {
        $user   = User::factory()->create(['role' => $role]);
        $token  = $user->createToken('test')->plainTextToken;
        $class  = SchoolClass::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/classes/' . $class->id, ['name' => 'Hacked Class'])
            ->assertStatus(403);
    }

    // ── Unauthorized roles cannot delete classes ───────────────────────────────

    /**
     * @dataProvider rolesBlockedFromClassWrites
     */
    public function test_role_cannot_delete_class(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;
        $class = SchoolClass::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/classes/' . $class->id)
            ->assertStatus(403);
    }

    // ── Unauthorized roles cannot list classes ─────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromClassList
     */
    public function test_role_cannot_list_classes(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes')
            ->assertStatus(403);
    }

    // ── Authorized roles CAN access ────────────────────────────────────────────

    public function test_admin_can_list_and_create_classes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes')
            ->assertStatus(200);
    }

    public function test_secretariat_can_list_classes(): void
    {
        $user  = User::factory()->create(['role' => 'secretariat']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromClassWrites(): array
    {
        // Route group 2: admin,secretariat,...,accountant — accountant IS allowed
        return [
            'teacher'    => ['teacher'],
            'parent'     => ['parent'],
            'supervisor' => ['supervisor'],
        ];
    }

    public static function rolesBlockedFromClassList(): array
    {
        // Teachers can list classes for context. Supervisors can see classes.
        // Parents are NOT in any group that allows /api/classes index.
        return [
            'parent' => ['parent'],
        ];
    }
}
