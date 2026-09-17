<?php

namespace Tests\Feature\BusinessLogic;

use Tests\TestCase;
use App\Models\User;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\Fee;
use App\Models\ParentFee;
use App\Models\Contract;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\AcademicYear;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentLogicTest extends TestCase
{
    use RefreshDatabase;

    protected $accountantUser;
    protected $parent;
    protected $contract;
    protected $bill;
    protected $payment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create accountant
        $this->accountantUser = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        
        $academicYear = AcademicYear::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->parent = ParentModel::factory()->create();
        $this->contract = Contract::factory()->create([
            'parent_id' => $this->parent->id,
            'academic_year_id' => $academicYear->id,
            'total_fees' => 1000,
            'remaining_amount' => 1000,
            'balance' => 0,
        ]);
        $this->bill = Bill::factory()->create([
            'contract_id' => $this->contract->id,
            'amount_due' => 500,
            'amount_paid' => 0,
            'balance' => 500,
            'status' => 'unpaid',
        ]);
        
        // Pay 100
        $this->payment = Payment::factory()->create([
            'contract_id' => $this->contract->id,
            'amount' => 100,
            'payment_type' => 'cash',
            'status' => 'completed',
        ]);
        PaymentAllocation::create([
            'payment_id' => $this->payment->id,
            'bill_id' => $this->bill->id,
            'amount' => 100
        ]);
        
        $this->bill->update(['amount_paid' => 100]);
        $this->contract->update(['paid_amount' => 100, 'remaining_amount' => 900]);
    }

    public function test_prevent_double_refund()
    {
        $this->actingAs($this->accountantUser);

        // Refund the payment
        $response1 = $this->postJson("/api/payments/{$this->payment->id}/refund", [
            'refund_amount' => 100,
            'refund_reason' => 'Test refund'
        ]);

        $response1->assertStatus(200);
        $this->payment->refresh();
        $this->assertEquals('refunded', $this->payment->status);

        // Try to refund again
        $response2 = $this->postJson("/api/payments/{$this->payment->id}/refund", [
            'refund_amount' => 100,
            'refund_reason' => 'Double refund exploit'
        ]);

        $response2->assertStatus(422)
                 ->assertJsonPath('message', __('messages.payment_already_refunded', ['default' => 'Payment has already been refunded']));

        // Try to delete refunded payment
        $response3 = $this->deleteJson("/api/payments/{$this->payment->id}");
        $response3->assertStatus(422)
                 ->assertJsonPath('message', __('messages.cannot_delete_refunded_payment', ['default' => 'Cannot delete a payment that has been refunded.']));
    }
}
