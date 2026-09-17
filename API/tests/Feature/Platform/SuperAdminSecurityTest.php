<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $tenant1;
    protected $tenant2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant1 = Tenant::create(['id' => 'school1']);
        $this->tenant2 = Tenant::create(['id' => 'school2']);
        
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'username' => 'admin_school1',
            'tenant_id' => 'school1'
        ]);
    }

    public function test_admin_cannot_access_other_tenants_data_via_superadmin_routes()
    {
        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/api/central/tenants/{$this->tenant2->id}");
            
        // Should be forbidden by SuperAdminOnly middleware
        $response->assertStatus(403);
    }
    
    public function test_inactive_tenant_director_sees_specific_login_message()
    {
        $inactiveTenant = Tenant::create(['id' => 'inactive_school', 'is_active' => false]);
        
        tenancy()->initialize($inactiveTenant);
        
        $director = User::factory()->create([
            'role' => 'primary_director',
            'username' => 'director_inactive',
            'password' => bcrypt('Password123'),
            'tenant_id' => 'inactive_school'
        ]);
        
        $response = $this->postJson('/api/central/login', [
            'username' => 'director_inactive',
            'password' => 'Password123'
        ]);
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'tenant_inactive' => true,
                // Assert it gives the admin/director message
                'message' => trans('messages.subscription_expired_admin')
            ]);
            
        tenancy()->end();
    }
}
