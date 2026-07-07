<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Payment;
use App\Models\Contract;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can create payment (new contract-based architecture)
     */
    public function test_admin_can_create_payment(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        $token    = $admin->createToken('test-token')->plainTextToken;
        $contract = Contract::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', [
                'contract_id'  => $contract->id,
                'amount'       => 500.00,
                'payment_type' => 'cash',
                'paid_date'    => now()->format('Y-m-d H:i:s'),
                'note'         => 'Test payment',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('payments', [
            'contract_id' => $contract->id,
        ]);
    }

    /**
     * Test admin can list payments
     */
    public function test_admin_can_list_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        Payment::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/payments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can update a payment
     */
    public function test_can_update_payment(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $token   = $admin->createToken('test-token')->plainTextToken;
        $payment = Payment::factory()->create(['status' => 'pending']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/payments/' . $payment->id, [
                'contract_id'  => $payment->contract_id,
                'amount'       => $payment->amount,
                'payment_type' => $payment->payment_type,
                'paid_date'    => now()->format('Y-m-d H:i:s'),
                'status'       => 'completed',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => 'completed',
        ]);
    }

    /**
     * Test teacher cannot create payments
     */
    public function test_teacher_cannot_create_payments(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token   = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', [
                'contract_id' => 1,
                'amount'      => 500,
            ]);

        $response->assertStatus(403);
    }
}
