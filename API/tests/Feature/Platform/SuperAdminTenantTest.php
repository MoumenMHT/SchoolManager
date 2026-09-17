<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTenantTest extends TestCase
{
    use RefreshDatabase;

    protected $superadmin;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->superadmin = User::factory()->create([
            'role' => 'superadmin',
            'username' => 'super_test',
        ]);
        
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'username' => 'admin_test',
        ]);
    }

    public function test_superadmin_can_list_tenants()
    {
        $response = $this->actingAs($this->superadmin, 'web')
            ->getJson('/api/central/tenants');
            
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'is_active', 'subscription_plan']
                    ]
                ]
            ]);
    }

    public function test_non_superadmin_cannot_list_tenants()
    {
        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/central/tenants');
            
        $response->assertStatus(403);
    }

    public function test_superadmin_can_create_tenant()
    {
        $payload = [
            'id' => 'new_school',
            'name' => 'New Test School',
            'domain' => 'newschool',
            'contact_name' => 'John Doe',
            'contact_email' => 'john@newschool.com',
            'subscription_plan' => 'premium',
            'max_students' => 500,
        ];

        $response = $this->actingAs($this->superadmin, 'web')
            ->postJson('/api/central/tenants', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tenants', [
            'id' => 'new_school',
            'subscription_plan' => 'premium',
        ]);
    }

    public function test_superadmin_can_update_tenant()
    {
        $tenant = Tenant::create(['id' => 'update_school']);
        
        $response = $this->actingAs($this->superadmin, 'web')
            ->putJson("/api/central/tenants/{$tenant->id}", [
                'name' => 'Updated School Name',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tenants', [
            'id' => 'update_school',
        ]);
        
        $updatedTenant = Tenant::find('update_school');
        $this->assertEquals('Updated School Name', $updatedTenant->name);
    }

    public function test_superadmin_can_activate_and_deactivate_tenant()
    {
        $tenant = Tenant::create(['id' => 'status_school', 'is_active' => true]);
        
        // Deactivate
        $response = $this->actingAs($this->superadmin, 'web')
            ->postJson("/api/central/tenants/{$tenant->id}/deactivate");
            
        $response->assertStatus(200);
        $this->assertDatabaseHas('tenants', [
            'id' => 'status_school',
            'is_active' => 0
        ]);

        // Activate
        $response = $this->actingAs($this->superadmin, 'web')
            ->postJson("/api/central/tenants/{$tenant->id}/activate");
            
        $response->assertStatus(200);
        $this->assertDatabaseHas('tenants', [
            'id' => 'status_school',
            'is_active' => 1
        ]);
    }
}
