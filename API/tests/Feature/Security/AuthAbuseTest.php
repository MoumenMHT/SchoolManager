<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthAbuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rate_limiting(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
        ]);

        // Route::post('/login')->middleware('throttle:5,1');
        // We will make 6 failed login attempts

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', [
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ]);
            $response->assertStatus(401);
        }

        // The 6th attempt should be blocked by rate limiter
        $response = $this->postJson('/api/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);
        
        $response->assertStatus(429); // Too Many Requests
    }
}
