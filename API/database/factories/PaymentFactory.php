<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $paidDate = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'contract_id'  => Contract::factory(),
            'amount'       => fake()->randomFloat(2, 500, 5500),
            'payment_type' => fake()->randomElement(['cash', 'bank_transfer', 'cheque', 'online']),
            'paid_date'    => $paidDate,
            'status'       => 'completed',
            'note'         => fake()->optional(0.4)->sentence(),
        ];
    }

    /** A pending/scheduled payment (not yet processed) */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status'    => 'pending',
            'paid_date' => null,
        ]);
    }

    /** A refunded payment (negative amount) */
    public function refund(): static
    {
        return $this->state(fn () => [
            'status'       => 'refunded',
            'payment_type' => 'refund',
            'amount'       => -fake()->randomFloat(2, 100, 3000),
            'note'         => 'Refund: ' . fake()->sentence(),
        ]);
    }
}
