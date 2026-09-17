<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that only admin and secretariat roles can manage users.
 * All other roles must receive 403 Forbidden.
 */
class UserManagementAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Roles that MUST be blocked ─────────────────────────────────────────────

    /**
     * @dataProvider unauthorizedRoles
     */
    public function test_unauthorized_role_cannot_list_users(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    /**
     * @dataProvider unauthorizedRoles
     */
    public function test_unauthorized_role_cannot_create_user(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/users', ['username' => 'newuser', 'password' => 'Pass123!'])
            ->assertStatus(403);
    }

    /**
     * @dataProvider unauthorizedRoles
     */
    public function test_unauthorized_role_cannot_update_user(string $role): void
    {
        $user      = User::factory()->create(['role' => $role]);
        $token     = $user->createToken('test')->plainTextToken;
        $otherUser = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $otherUser->id, ['username' => 'changed'])
            ->assertStatus(403);
    }

    /**
     * @dataProvider unauthorizedRoles
     */
    public function test_unauthorized_role_cannot_delete_user(string $role): void
    {
        $user      = User::factory()->create(['role' => $role]);
        $token     = $user->createToken('test')->plainTextToken;
        $otherUser = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/users/' . $otherUser->id)
            ->assertStatus(403);
    }

    /**
     * @dataProvider unauthorizedRoles
     */
    public function test_unauthorized_role_cannot_register_new_user(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/register', [
                'username'              => 'brandnew',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'role'                  => 'teacher',
            ])
            ->assertStatus(403);
    }

    /**
     * @dataProvider unauthorizedRoles
     */
    public function test_unauthorized_role_cannot_update_user_credentials(string $role): void
    {
        $user      = User::factory()->create(['role' => $role]);
        $token     = $user->createToken('test')->plainTextToken;
        $otherUser = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/users/' . $otherUser->id . '/credentials', [
                'password'              => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertStatus(403);
    }

    // ── Roles that MUST have access ────────────────────────────────────────────

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users')
            ->assertStatus(200);
    }

    public function test_secretariat_can_list_users(): void
    {
        $user  = User::factory()->create(['role' => 'secretariat']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function unauthorizedRoles(): array
    {
        // Route group: role:admin,secretariat — everyone else blocked
        return [
            'teacher'          => ['teacher'],
            'parent'           => ['parent'],
            'supervisor'       => ['supervisor'],
            'accountant'       => ['accountant'],
            'primary_director' => ['primary_director'],
            'cem_director'     => ['cem_director'],
            'lycee_director'   => ['lycee_director'],
        ];
    }
}
