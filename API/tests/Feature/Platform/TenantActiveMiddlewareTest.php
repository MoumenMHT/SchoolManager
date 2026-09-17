<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantActiveMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_active_tenant_allows_access_to_api()
    {
        // 1. Create a real tenant that is ACTIVE
        $tenant = Tenant::create(['id' => 'active_school', 'is_active' => true]);
        
        $this->tenant = $tenant; // TestCase will use this for the path rewrite
        
        // 2. Initialize tenancy and create a user IN that tenant's scope
        tenancy()->initialize($tenant);
        
        $user = User::factory()->create([
            'role' => 'admin',
            'username' => 'active_admin',
            'tenant_id' => 'active_school'
        ]);

        // 3. Act as user and hit an API route
        $response = $this->actingAs($user, 'web')
            ->getJson('/api/me');
            
        $response->assertStatus(200);
        
        tenancy()->end();
    }

    public function test_inactive_tenant_blocks_access_to_api()
    {
        // 1. Create a real tenant that is INACTIVE
        $tenant = Tenant::create(['id' => 'inactive_school', 'is_active' => false]);
        
        $this->tenant = $tenant;
        
        // 2. Initialize tenancy and create a user
        tenancy()->initialize($tenant);
        
        $user = User::factory()->create([
            'role' => 'admin',
            'username' => 'inactive_admin',
            'tenant_id' => 'inactive_school'
        ]);

        // 3. Act as user and hit an API route
        $response = $this->actingAs($user, 'web')
            ->getJson('/api/me');
            
        // Should be blocked by CheckTenantActive middleware
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'tenant_inactive' => true
            ]);
            
        tenancy()->end();
    }
}
