<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        $amountDue  = fake()->randomElement([3500, 3780, 4100, 4410, 4900]) * 1.0;
        $amountPaid = 0.0;
        $dueDate    = Carbon::parse(fake()->dateTimeBetween('2025-09-30', '2026-06-30'))->endOfMonth();
        $isPast     = $dueDate->isPast();

        $status = $isPast ? 'late' : 'unpaid';

        return [
            'contract_id' => Contract::factory(),
            'month_year'  => $dueDate->copy()->startOfMonth()->format('F Y'),
            'amount_due'  => $amountDue,
            'amount_paid' => $amountPaid,
            'balance'     => $amountDue,
            'status'      => $status,
            'due_date'    => $dueDate,
            'note'        => null,
        ];
    }

    /** Bill fully paid */
    public function paid(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'amount_paid' => $attrs['amount_due'],
                'balance'     => 0,
                'status'      => 'paid',
            ];
        });
    }

    /** Bill partially paid (50 % by default) */
    public function partial(float $fraction = 0.5): static
    {
        return $this->state(function (array $attrs) use ($fraction) {
            $paid = round($attrs['amount_due'] * $fraction, 2);
            return [
                'amount_paid' => $paid,
                'balance'     => $attrs['amount_due'] - $paid,
                'status'      => 'partial',
            ];
        });
    }

    /** Bill overdue (past due date, no payment) */
    public function late(): static
    {
        return $this->state(fn () => [
            'due_date' => Carbon::parse('2025-11-30'),
            'status'   => 'late',
        ]);
    }
}
