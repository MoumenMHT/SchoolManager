<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Models\Tenant;
use App\Models\PlatformPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $superadmin;
    protected $admin;
    protected $tenant;

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
        
        $this->tenant = Tenant::create(['id' => 'pay_school']);
    }

    public function test_superadmin_can_list_payments()
    {
        PlatformPayment::create([
            'tenant_id' => $this->tenant->id,
            'amount' => 10000,
            'status' => 'completed',
            'payment_date' => now(),
            'period_start' => now(),
            'period_end' => now()->addYear(),
            'recorded_by' => $this->superadmin->id,
        ]);

        $response = $this->actingAs($this->superadmin, 'web')
            ->getJson('/api/central/payments');
            
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'tenant_id', 'amount', 'status']
                    ]
                ]
            ]);
    }

    public function test_non_superadmin_cannot_list_payments()
    {
        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/central/payments');
            
        $response->assertStatus(403);
    }

    public function test_superadmin_can_record_payment()
    {
        $payload = [
            'tenant_id' => $this->tenant->id,
            'amount' => 15000,
            'currency' => 'DZD',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2023-10-01',
            'period_start' => '2023-10-01',
            'period_end' => '2024-10-01',
            'reference' => 'TRX-12345',
            'notes' => 'Annual subscription',
        ];

        $response = $this->actingAs($this->superadmin, 'web')
            ->postJson('/api/central/payments', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('platform_payments', [
            'tenant_id' => $this->tenant->id,
            'amount' => 15000,
            'reference' => 'TRX-12345',
            'status' => 'completed',
            'recorded_by' => $this->superadmin->id
        ]);
        
        // Tenant should be updated implicitly by the payment recorded?
        // Actually, the PlatformPaymentController handles updating the tenant's last_payment_date.
    }
}
