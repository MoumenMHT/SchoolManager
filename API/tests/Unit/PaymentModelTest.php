<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test payment has correct fillable fields (new contract-based architecture)
     */
    public function test_payment_has_correct_fillable_fields(): void
    {
        $fillable = ['contract_id', 'amount', 'payment_type', 'status', 'paid_date', 'note'];
        $payment = new Payment();

        $this->assertEquals($fillable, $payment->getFillable());
    }

    /**
     * Test payment belongs to contract (new architecture)
     */
    public function test_payment_belongs_to_contract(): void
    {
        $contract = Contract::factory()->create();
        $payment = Payment::factory()->create(['contract_id' => $contract->id]);

        $this->assertInstanceOf(Contract::class, $payment->contract);
        $this->assertEquals($contract->id, $payment->contract->id);
    }

    /**
     * Test payment casts paid_date as datetime
     */
    public function test_payment_casts_dates(): void
    {
        $payment = Payment::factory()->create([
            'paid_date' => '2026-02-15 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $payment->paid_date);
    }

    /**
     * Test payment casts amount to decimal
     */
    public function test_payment_casts_amount_to_decimal(): void
    {
        $payment = Payment::factory()->create(['amount' => 500.50]);

        $this->assertEquals(500.50, (float) $payment->amount);
    }

    /**
     * Test payment status values
     */
    public function test_payment_status_values(): void
    {
        $completed = Payment::factory()->create(['status' => 'completed']);
        $pending   = Payment::factory()->create(['status' => 'pending']);

        $this->assertEquals('completed', $completed->status);
        $this->assertEquals('pending', $pending->status);
    }
}
