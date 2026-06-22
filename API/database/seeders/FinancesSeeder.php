<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Contract;
use App\Models\Fee;
use App\Models\ParentFee;
use App\Models\ParentModel;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinancesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💰 Seeding finances (contracts, bills, payments)…');

        // ── 1. Base Fees ──────────────────────────────────────────────────────
        $fees = [
            ['name' => 'رسوم التسجيل',  'description' => 'رسوم التسجيل السنوية',    'base_amount' => 5000,  'academic_year' => '2025-2026', 'is_active' => true],
            ['name' => 'رسوم الدراسة',  'description' => 'الأقساط الشهرية',          'base_amount' => 15000, 'academic_year' => '2025-2026', 'is_active' => true],
            ['name' => 'المطعم',        'description' => 'خدمة الإطعام المدرسي',    'base_amount' => 6000,  'academic_year' => '2025-2026', 'is_active' => true],
            ['name' => 'النقل المدرسي', 'description' => 'خدمة النقل المدرسي',       'base_amount' => 4000,  'academic_year' => '2025-2026', 'is_active' => true],
            ['name' => 'الكتب والقرطاسية', 'description' => 'الكتب والأدوات المدرسية', 'base_amount' => 3000, 'academic_year' => '2025-2026', 'is_active' => true],
        ];

        $createdFees = [];
        foreach ($fees as $feeData) {
            $createdFees[] = Fee::create($feeData);
        }

        $feeRegistration = $createdFees[0]; // one-time
        $feeTuition      = $createdFees[1]; // monthly base
        $feeCantine      = $createdFees[2]; // optional monthly
        $feeTransport    = $createdFees[3]; // optional monthly
        $feeBooks        = $createdFees[4]; // one-time

        $parents = ParentModel::with('students.class.levelProfile')->get();

        if ($parents->isEmpty()) {
            $this->command->info('No parents found. Skipping finances seeding.');
            return;
        }

        $paymentTypes = ['cash', 'bank_transfer', 'cheque'];

        // Payment scenarios – randomly assigned per parent
        // 1 = fully up to date (paid 6+ months)
        // 2 = partially paid (3-5 months)
        // 3 = late / mostly unpaid (1-2 months)
        $scenarioWeights = [1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3]; // 50% up-to-date, 33% partial, 17% late

        DB::beginTransaction();
        try {
            foreach ($parents as $parent) {
                if ($parent->students->isEmpty()) continue;

                // ── 2. ParentFee entries ────────────────────────────────────
                $monthlyFeeTotal     = 0;
                $oneTimeFeeTotal     = 0;

                foreach ($parent->students as $student) {
                    // Everyone: tuition + registration + books
                    ParentFee::create([
                        'parent_id'  => $parent->id,
                        'student_id' => $student->id,
                        'fee_id'     => $feeRegistration->id,
                    ]);
                    ParentFee::create([
                        'parent_id'  => $parent->id,
                        'student_id' => $student->id,
                        'fee_id'     => $feeTuition->id,
                    ]);
                    ParentFee::create([
                        'parent_id'  => $parent->id,
                        'student_id' => $student->id,
                        'fee_id'     => $feeBooks->id,
                    ]);

                    $monthlyFeeTotal += $feeTuition->base_amount;
                    $oneTimeFeeTotal += $feeRegistration->base_amount + $feeBooks->base_amount;

                    // Cantine: 60% chance
                    if (rand(1, 100) <= 60) {
                        ParentFee::create([
                            'parent_id'  => $parent->id,
                            'student_id' => $student->id,
                            'fee_id'     => $feeCantine->id,
                        ]);
                        $monthlyFeeTotal += $feeCantine->base_amount;
                    }

                    // Transport: 40% chance
                    if (rand(1, 100) <= 40) {
                        ParentFee::create([
                            'parent_id'  => $parent->id,
                            'student_id' => $student->id,
                            'fee_id'     => $feeTransport->id,
                        ]);
                        $monthlyFeeTotal += $feeTransport->base_amount;
                    }
                }

                // Total: one-time (paid in Sept) + 10 monthly payments (Sept–June)
                $totalContractFees = $oneTimeFeeTotal + ($monthlyFeeTotal * 10);
                $monthlyAmount     = $monthlyFeeTotal;

                // ── 3. Contract ─────────────────────────────────────────────
                $contract = Contract::create([
                    'parent_id'        => $parent->id,
                    'academic_year'    => '2025-2026',
                    'total_fees'       => $totalContractFees,
                    'monthly_amount'   => $monthlyAmount,
                    'paid_amount'      => 0,
                    'remaining_amount' => $totalContractFees,
                    'balance'          => $totalContractFees,
                    'start_date'       => '2025-09-01',
                    'end_date'         => '2026-06-30',
                    'status'           => 'active',
                    'is_active'        => true,
                    'notes'            => 'عقد السنة الدراسية 2025-2026',
                ]);

                // ── 4. Bills (10 monthly) ────────────────────────────────────
                $bills = [];
                for ($month = 9; $month <= 18; $month++) {
                    $realMonth = $month > 12 ? $month - 12 : $month;
                    $year      = $month > 12 ? 2026 : 2025;
                    $dueDate   = Carbon::createFromDate($year, $realMonth, 5);

                    // September bill includes one-time fees
                    $amountDue = ($month === 9)
                        ? $monthlyAmount + $oneTimeFeeTotal
                        : $monthlyAmount;

                    $bills[] = Bill::create([
                        'contract_id' => $contract->id,
                        'month_year'  => $dueDate->format('m/Y'),
                        'amount_due'  => $amountDue,
                        'amount_paid' => 0,
                        'balance'     => $amountDue,
                        'status'      => 'unpaid',
                        'due_date'    => $dueDate->toDateString(),
                    ]);
                }

                // ── 5. Payments (scenario-driven) ────────────────────────────
                $scenario     = $scenarioWeights[array_rand($scenarioWeights)];
                $paidMonths   = match ($scenario) {
                    1 => rand(5, 8),   // up-to-date: 5–8 months paid
                    2 => rand(2, 4),   // partial: 2–4 months
                    3 => rand(0, 1),   // late: 0–1 month
                    default => 3,
                };

                $totalPaid = 0;

                foreach ($bills as $index => $bill) {
                    if ($index >= $paidMonths) break;

                    $paymentDate = Carbon::parse($bill->due_date)->subDays(rand(0, 5));
                    $payAmount   = (float) $bill->amount_due;

                    // Occasionally make a partial payment on the last paid bill
                    if ($index === $paidMonths - 1 && rand(0, 1) && $scenario !== 1) {
                        $payAmount = round($payAmount * (rand(30, 80) / 100), 2);
                    }

                    $payment = Payment::create([
                        'contract_id'  => $contract->id,
                        'amount'       => $payAmount,
                        'payment_type' => $paymentTypes[array_rand($paymentTypes)],
                        'status'       => 'completed',
                        'paid_date'    => $paymentDate->toDateString(),
                    ]);

                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'bill_id'    => $bill->id,
                        'amount'     => $payAmount,
                    ]);

                    // Update bill
                    $bill->amount_paid = $payAmount;
                    $bill->balance     = max(0, (float) $bill->amount_due - $payAmount);
                    $bill->status      = ($bill->balance <= 0) ? 'paid' : 'partial';
                    $bill->save();

                    $totalPaid += $payAmount;
                }

                // Mark overdue bills
                foreach ($bills as $bill) {
                    if (Carbon::parse($bill->due_date)->isPast() && (float) $bill->balance > 0) {
                        $bill->status = ($bill->amount_paid > 0) ? 'partial' : 'late';
                        $bill->save();
                    }
                }

                // Update contract totals
                $contract->paid_amount      = $totalPaid;
                $contract->remaining_amount = max(0, (float) $totalContractFees - $totalPaid);
                $contract->balance          = (float) $totalContractFees - $totalPaid;
                $contract->status           = ($totalPaid >= $totalContractFees) ? 'completed' : 'active';
                $contract->save();
            }

            DB::commit();
            $this->command->info('✅ Finances seed complete!');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('❌ Finances seeding failed: ' . $e->getMessage());
            $this->command->error($e->getTraceAsString());
        }
    }
}
