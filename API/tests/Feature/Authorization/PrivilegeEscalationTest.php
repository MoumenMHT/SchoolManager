<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that users cannot escalate their own privileges.
 *
 * Security scenarios tested:
 * - User cannot change their own role via PUT /api/users/{id}
 * - User cannot update another user's role if not admin
 * - User cannot grant themselves a higher-privilege role
 * - Admin cannot set role to 'superadmin' or other invalid roles
 */
class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    // ── Self role escalation ───────────────────────────────────────────────────

    public function test_teacher_cannot_promote_themselves_to_admin(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token   = $teacher->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $teacher->id, [
                'role' => 'admin',
            ])
            ->assertStatus(403);

        // Role must remain 'teacher' in the database
        $this->assertDatabaseHas('users', [
            'id'   => $teacher->id,
            'role' => 'teacher',
        ]);
    }

    public function test_parent_cannot_promote_themselves_to_admin(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $token  = $parent->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $parent->id, [
                'role' => 'admin',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id'   => $parent->id,
            'role' => 'parent',
        ]);
    }

    public function test_accountant_cannot_promote_themselves_to_admin(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $token      = $accountant->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $accountant->id, [
                'role' => 'admin',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id'   => $accountant->id,
            'role' => 'accountant',
        ]);
    }

    public function test_supervisor_cannot_promote_themselves_to_admin(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $token      = $supervisor->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $supervisor->id, [
                'role' => 'admin',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id'   => $supervisor->id,
            'role' => 'supervisor',
        ]);
    }

    // ── Cross-user role escalation ─────────────────────────────────────────────

    public function test_teacher_cannot_elevate_another_user_to_admin(): void
    {
        $teacher   = User::factory()->create(['role' => 'teacher']);
        $victim    = User::factory()->create(['role' => 'parent']);
        $token     = $teacher->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $victim->id, [
                'role' => 'admin',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id'   => $victim->id,
            'role' => 'parent',
        ]);
    }

    public function test_parent_cannot_change_another_users_role(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $victim = User::factory()->create(['role' => 'teacher']);
        $token  = $parent->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $victim->id, [
                'role' => 'admin',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id'   => $victim->id,
            'role' => 'teacher',
        ]);
    }

    // ── Admin cannot set invalid elevated roles ────────────────────────────────

    public function test_admin_cannot_create_superadmin_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/register', [
                'username'              => 'evil',
                'email'                 => 'evil@example.com',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'role'                  => 'superadmin',
            ]);

        // Must reject invalid role — 422 (validation) or 403 (policy)
        $this->assertContains($response->status(), [403, 422]);

        $this->assertDatabaseMissing('users', ['role' => 'superadmin']);
    }

    public function test_admin_cannot_update_user_to_superadmin_role(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'teacher']);
        $token  = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $target->id, [
                'role' => 'superadmin',
            ]);

        $this->assertContains($response->status(), [403, 422]);

        $this->assertDatabaseMissing('users', [
            'id'   => $target->id,
            'role' => 'superadmin',
        ]);
    }

    // ── Admin CAN legitimately update roles ────────────────────────────────────

    public function test_admin_can_change_user_role_to_valid_role(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'teacher']);
        $token  = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $target->id, [
                'username' => $target->username,
                'role'     => 'secretariat',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'   => $target->id,
            'role' => 'secretariat',
        ]);
    }
}
