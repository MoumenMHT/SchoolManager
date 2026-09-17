<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->superadmin = User::factory()->create([
            'role' => 'superadmin',
            'username' => 'super_dash',
        ]);
    }

    public function test_dashboard_stats_returns_metrics()
    {
        Tenant::create(['id' => 'school1', 'is_active' => true]);
        Tenant::create(['id' => 'school2', 'is_active' => false]);
        
        // Disable tenancy for users creation since we are checking global stats
        $user1 = User::factory()->create(['role' => 'admin', 'tenant_id' => 'school1']);
        $user2 = User::factory()->create(['role' => 'teacher', 'tenant_id' => 'school1']);
        
        $response = $this->actingAs($this->superadmin, 'web')
            ->getJson('/api/central/dashboard/stats');
            
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenants' => [
                        'total',
                        'active',
                    ],
                    'revenue' => [
                        'total'
                    ],
                    'platform' => [
                        'total_users',
                        'total_students',
                    ]
                ]
            ]);
            
        $this->assertEquals(3, $response->json('data.tenants.total'));
        $this->assertEquals(2, $response->json('data.tenants.active'));
        // 2 users: user1, user2 (superadmin is excluded from total_users)
        $this->assertEquals(2, $response->json('data.platform.total_users'));
    }
}
