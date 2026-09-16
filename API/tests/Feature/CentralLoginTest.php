<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_centrally_and_get_tenant_id(): void
    {
        $user = User::factory()->create([
            'username' => 'centraluser',
            'password' => bcrypt('password123'),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/central/login', [
            'username' => 'centraluser',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user',
                'tenant_id'
            ])
            ->assertJsonPath('tenant_id', $this->tenant->id);
    }

    public function test_central_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'centraluser',
            'password' => bcrypt('password123'),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/central/login', [
            'username' => 'centraluser',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }
}
