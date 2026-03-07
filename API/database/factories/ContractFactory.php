<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ParentModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $grossMonthly = fake()->randomElement([3500, 4300, 4100, 4900, 5300]);
        $months       = 10;
        $grossTotal   = $grossMonthly * $months;
        $discPct      = fake()->randomElement([0, 0, 0, 5, 10]);
        $discAmt      = round($grossTotal * $discPct / 100, 2);
        $netTotal     = $grossTotal - $discAmt;
        $netMonthly   = round($netTotal / $months, 2);
        $paidMonths   = fake()->numberBetween(0, $months);
        $paidAmount   = $netMonthly * $paidMonths;

        return [
            'parent_id'        => ParentModel::factory(),
            'academic_year'    => '2025-2026',
            'total_fees'       => $grossTotal,
            'discount_type'    => $discPct > 0 ? 'percentage' : null,
            'discount_value'   => $discAmt,
            'discount_reason'  => $discPct > 0 ? fake()->randomElement(['Sibling discount', 'Financial hardship']) : null,
            'monthly_amount'   => $netMonthly,
            'paid_amount'      => $paidAmount,
            'remaining_amount' => max(0, $netTotal - $paidAmount),
            'balance'          => 0,
            'start_date'       => Carbon::parse('2025-09-01'),
            'end_date'         => Carbon::parse('2026-06-30'),
            'status'           => 'active',
            'notes'            => fake()->optional(0.3)->sentence(),
        ];
    }

    /** Mark the contract as fully paid */
    public function fullyPaid(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'paid_amount'      => $attrs['total_fees'] - $attrs['discount_value'],
                'remaining_amount' => 0,
            ];
        });
    }

    /** Mark the contract as completed (end of year) */
    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
