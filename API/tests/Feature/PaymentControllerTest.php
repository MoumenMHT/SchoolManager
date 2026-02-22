<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can create payment
     */
    public function test_admin_can_create_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', [
                'student_id' => $student->id,
                'amount' => 500.00,
                'due_date' => now()->addMonth()->format('Y-m-d'),
                'status' => 'pending',
                'payment_type' => 'monthly',
                'academic_year' => '2025-2026',
                'month' => 'January',
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('payments', [
            'student_id' => $student->id,
            'amount' => 500.00,
        ]);
    }

    /**
     * Test can get student payments
     */
    public function test_can_get_student_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        Payment::factory()->count(5)->create(['student_id' => $student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/students/' . $student->id . '/payments');

        $response->assertStatus(200);
    }

    /**
     * Test can update payment
     */
    public function test_can_update_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $payment = Payment::factory()->create(['status' => 'pending']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/payments/' . $payment->id, [
                'student_id' => $payment->student_id,
                'amount' => $payment->amount,
                'due_date' => $payment->due_date->format('Y-m-d'),
                'paid_date' => now()->format('Y-m-d'),
                'status' => 'paid',
                'payment_type' => $payment->payment_type,
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);
    }

    /**
     * Test teacher cannot create payments
     */
    public function test_teacher_cannot_create_payments(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', [
                'student_id' => 1,
                'amount' => 500,
            ]);

        $response->assertStatus(403);
    }
}
