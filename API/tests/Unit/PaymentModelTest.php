<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test payment has correct fillable fields
     */
    public function test_payment_has_correct_fillable_fields(): void
    {
        $fillable = ['student_id', 'amount', 'due_date', 'paid_date', 'status', 'payment_type', 'academic_year', 'month', 'notes'];
        $payment = new Payment();
        
        $this->assertEquals($fillable, $payment->getFillable());
    }

    /**
     * Test payment belongs to student
     */
    public function test_payment_belongs_to_student(): void
    {
        $student = Student::factory()->create();
        $payment = Payment::factory()->create(['student_id' => $student->id]);
        
        $this->assertInstanceOf(Student::class, $payment->student);
        $this->assertEquals($student->id, $payment->student->id);
    }

    /**
     * Test payment casts dates
     */
    public function test_payment_casts_dates(): void
    {
        $payment = Payment::factory()->create([
            'due_date' => '2026-02-28',
            'paid_date' => '2026-02-15',
        ]);
        
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $payment->due_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $payment->paid_date);
    }

    /**
     * Test payment casts amount to decimal
     */
    public function test_payment_casts_amount_to_decimal(): void
    {
        $payment = Payment::factory()->create(['amount' => 500.50]);
        
        $this->assertEquals(500.50, (float)$payment->amount);
    }

    /**
     * Test payment status values
     */
    public function test_payment_status_values(): void
    {
        $pending = Payment::factory()->create(['status' => 'pending']);
        $paid = Payment::factory()->create(['status' => 'paid']);
        
        $this->assertEquals('pending', $pending->status);
        $this->assertEquals('paid', $paid->status);
    }
}
