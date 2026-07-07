<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can login with valid credentials (username-based login)
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('Password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'testuser',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'user' => [
                    'id',
                    'username',
                    'role',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * Test user cannot login with invalid credentials
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('Password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test login requires username or phone
     */
    public function test_login_requires_username_or_phone(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'Password123',
        ]);

        // Without username or phone, validation returns 422
        $response->assertStatus(422);
    }

    /**
     * Test login requires password
     */
    public function test_login_requires_password(): void
    {
        $response = $this->postJson('/api/login', [
            'username' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test authenticated user can logout
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(0, $user->tokens);
    }

    /**
     * Test unauthenticated user cannot logout
     */
    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    /**
     * Test authenticated user can get their profile
     */
    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user'    => [
                    'username' => 'testuser',
                ],
            ]);
    }

    /**
     * Test authenticated user can change password (password must meet complexity rules)
     */
    public function test_authenticated_user_can_change_password(): void
    {
        $user  = User::factory()->create([
            'password' => bcrypt('OldPassword123'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/change-password', [
                'current_password'          => 'OldPassword123',
                'new_password'              => 'NewPassword456',
                'new_password_confirmation' => 'NewPassword456',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify old password no longer works
        $loginResponse = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'OldPassword123',
        ]);
        $loginResponse->assertStatus(401);

        // Verify new password works
        $loginResponse = $this->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'NewPassword456',
        ]);
        $loginResponse->assertStatus(200);
    }

    /**
     * Test change password requires correct current password
     */
    public function test_change_password_requires_correct_current_password(): void
    {
        $user  = User::factory()->create([
            'password' => bcrypt('OldPassword123'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/change-password', [
                'current_password'          => 'WrongPassword1',
                'new_password'              => 'NewPassword456',
                'new_password_confirmation' => 'NewPassword456',
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test admin can register new user (role=admin since register supports admin/teacher/parent)
     */
    public function test_admin_can_register_new_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        // Register an admin user (no teacher_id/parent_id required)
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/register', [
                'username'              => 'newadminuser',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'role'                  => 'admin',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'username' => 'newadminuser',
            'role'     => 'admin',
        ]);
    }

    /**
     * Test non-admin cannot register new user
     */
    public function test_non_admin_cannot_register_new_user(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token   = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/register', [
                'username'              => 'newuser',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'role'                  => 'teacher',
            ]);

        $response->assertStatus(403);
    }
}
