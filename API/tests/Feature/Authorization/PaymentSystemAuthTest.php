<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Fee;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that only admin and accountant roles can access the payment system.
 * All other roles must receive 403 Forbidden.
 */
class PaymentSystemAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Fee routes ─────────────────────────────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_list_fees(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/fees')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_create_fee(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/fees', ['name' => 'Tuition', 'base_amount' => 3000])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_update_fee(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;
        $fee   = Fee::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/fees/' . $fee->id, ['name' => 'Changed'])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_delete_fee(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;
        $fee   = Fee::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/fees/' . $fee->id)
            ->assertStatus(403);
    }

    // ── Contract routes ────────────────────────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_list_contracts(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/contracts')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_create_contract(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/contracts', [])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_delete_contract(string $role): void
    {
        $user     = User::factory()->create(['role' => $role]);
        $token    = $user->createToken('test')->plainTextToken;
        $contract = Contract::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/contracts/' . $contract->id)
            ->assertStatus(403);
    }

    // ── Payment routes ─────────────────────────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_list_payments(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/payments')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_create_payment(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', [])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_delete_payment(string $role): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $payment = Payment::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/payments/' . $payment->id)
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_get_financial_reports(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/payments/financial-reports')
            ->assertStatus(403);
    }

    // ── Bill routes ────────────────────────────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromPaymentSystem
     */
    public function test_role_cannot_list_bills(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/bills')
            ->assertStatus(403);
    }

    // ── Authorized roles CAN access ────────────────────────────────────────────

    public function test_admin_can_access_payment_system(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/payments')
            ->assertStatus(200);
    }

    public function test_accountant_can_access_payment_system(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $token      = $accountant->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/payments')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromPaymentSystem(): array
    {
        return [
            'teacher'          => ['teacher'],
            'parent'           => ['parent'],
            'supervisor'       => ['supervisor'],
            'secretariat'      => ['secretariat'],
            'primary_director' => ['primary_director'],
            'cem_director'     => ['cem_director'],
            'lycee_director'   => ['lycee_director'],
        ];
    }
}
