<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_allocation_has_correct_amount(): void
    {
        $payment = Payment::factory()->create(['amount' => 1000, 'tenant_id' => $this->tenant->id]);
        $bill = Bill::factory()->create(['amount_due' => 1000, 'amount_paid' => 0, 'tenant_id' => $this->tenant->id]);

        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'bill_id' => $bill->id,
            'amount' => 500,
            'tenant_id' => $this->tenant->id
        ]);

        $this->assertEquals(500, $allocation->amount);
        $this->assertEquals($payment->id, $allocation->payment->id);
        $this->assertEquals($bill->id, $allocation->bill->id);
    }
}
