<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_and_tenant_cannot_be_mass_assigned(): void
    {
        $user = new User();
        $user->fill([
            'username' => 'hacker',
            'email' => 'hacker@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'tenant_id' => 'hacked_tenant'
        ]);

        $this->assertNull($user->role);
        $this->assertNull($user->tenant_id);
        $this->assertEquals('hacker', $user->username);
    }
}
