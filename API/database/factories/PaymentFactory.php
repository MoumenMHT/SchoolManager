<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'amount' => fake()->randomFloat(2, 100, 1000),
            'due_date' => fake()->date(),
            'paid_date' => fake()->optional()->date(),
            'status' => fake()->randomElement(['paid', 'pending', 'late']),
            'payment_type' => fake()->randomElement(['monthly', 'registration', 'exam', 'book']),
            'academic_year' => '2025-2026',
            'month' => fake()->optional()->monthName(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
