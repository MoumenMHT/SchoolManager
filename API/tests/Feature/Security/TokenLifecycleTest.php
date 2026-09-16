<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_use_token(): void
    {
        $user = User::factory()->create();

        // Simulate token creation (like in a mobile login)
        $tokenResult = $user->createToken('mobile-app');
        $token = $tokenResult->plainTextToken;

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile-app'
        ]);

        // Verify the token works for protected routes
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');
            
        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $user->id);
    }

    public function test_token_revocation_on_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-app')->plainTextToken;

        // Perform logout with token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200);

        // Verify the token is deleted from the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id
        ]);

        // Clear in-memory auth caches
        $this->app->get('auth')->forgetGuards();

        // Verify the token can no longer access protected endpoints
        $subsequentResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $subsequentResponse->assertStatus(401);
    }
}
