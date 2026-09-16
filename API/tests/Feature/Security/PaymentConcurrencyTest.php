<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contract;
use App\Models\Bill;
use App\Models\Student;
use App\Models\ParentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class PaymentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_payments_do_not_double_allocate()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $parent = ParentModel::factory()->create();
        $student = Student::factory()->create();
        
        $contract = Contract::factory()->create([
            'parent_id' => $parent->id,
            'total_fees' => 1000,
            'paid_amount' => 0
        ]);

        $bill = Bill::factory()->create([
            'contract_id' => $contract->id,
            'amount_due' => 1000,
            'amount_paid' => 0,
            'status' => 'unpaid'
        ]);

        // Simulate concurrent execution by using DB transactions with parallel queries or multiple test requests
        // Since true concurrency is hard in PHPUnit, we'll verify the lockForUpdate syntax doesn't crash 
        // and does its job sequentially at least.
        
        $response1 = $this->actingAs($user, 'web')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'amount' => 500,
                'payment_type' => 'cash',
                'paid_date' => now()->format('Y-m-d')
            ]);
            
        $response1->assertStatus(201);

        $response2 = $this->actingAs($user, 'web')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'amount' => 500,
                'payment_type' => 'cash',
                'paid_date' => now()->format('Y-m-d')
            ]);
            
        $response2->assertStatus(201);

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'amount_paid' => 1000,
            'status' => 'paid'
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'paid_amount' => 1000
        ]);
    }
}
