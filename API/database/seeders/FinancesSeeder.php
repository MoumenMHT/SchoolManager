<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\Fee;
use App\Models\ParentFee;
use App\Models\Contract;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Starting finances seeding...');

        // 1. Create Base Fees
        $fees = [
            ['name' => 'Frais de Scolarité', 'description' => 'Frais de base annuels', 'base_amount' => 100000, 'academic_year' => '2025-2026', 'is_active' => true],
            ['name' => 'Cantine', 'description' => 'Frais de restauration', 'base_amount' => 30000, 'academic_year' => '2025-2026', 'is_active' => true],
            ['name' => 'Transport', 'description' => 'Transport scolaire', 'base_amount' => 20000, 'academic_year' => '2025-2026', 'is_active' => true],
        ];

        $createdFees = [];
        foreach ($fees as $feeData) {
            $createdFees[] = Fee::create($feeData);
        }

        $parents = ParentModel::with('students')->get();
        if ($parents->isEmpty()) {
            $this->command->info('No parents found. Skipping finances seeding.');
            return;
        }

        DB::beginTransaction();
        try {
            foreach ($parents as $parent) {
                // If the parent has no students, skip
                if ($parent->students->isEmpty()) {
                    continue;
                }

                // Calculate total fees based on children and add ParentFee records
                $totalContractFees = 0;
                foreach ($parent->students as $student) {
                    // Everyone gets tuition
                    ParentFee::create([
                        'parent_id' => $parent->id,
                        'student_id' => $student->id,
                        'fee_id' => $createdFees[0]->id, // Tuition
                    ]);
                    $totalContractFees += $createdFees[0]->base_amount;

                    // Randomly assign Cantine and Transport
                    if (rand(1, 100) > 50) {
                        ParentFee::create([
                            'parent_id' => $parent->id,
                            'student_id' => $student->id,
                            'fee_id' => $createdFees[1]->id, // Cantine
                        ]);
                        $totalContractFees += $createdFees[1]->base_amount;
                    }
                    if (rand(1, 100) > 70) {
                        ParentFee::create([
                            'parent_id' => $parent->id,
                            'student_id' => $student->id,
                            'fee_id' => $createdFees[2]->id, // Transport
                        ]);
                        $totalContractFees += $createdFees[2]->base_amount;
                    }
                }

                $monthlyAmount = $totalContractFees / 10;

                // 2. Create Contract (September to June)
                $contract = Contract::create([
                    'parent_id' => $parent->id,
                    'academic_year' => '2025-2026',
                    'total_fees' => $totalContractFees,
                    'monthly_amount' => $monthlyAmount,
                    'paid_amount' => 0,
                    'remaining_amount' => $totalContractFees,
                    'balance' => $totalContractFees,
                    'start_date' => '2025-09-01',
                    'end_date' => '2026-06-30',
                    'status' => 'active',
                    'is_active' => true,
                ]);

                // 3. Create 10 Monthly Bills (Sept to June)
                $bills = [];
                for ($month = 9; $month <= 18; $month++) { // 9 to 18 to handle crossing into next year
                    $realMonth = $month > 12 ? $month - 12 : $month;
                    $year = $month > 12 ? 2026 : 2025;
                    
                    $dueDate = Carbon::createFromDate($year, $realMonth, 5); // Due on 5th of the month
                    $monthYearStr = $dueDate->format('m/Y');

                    $bills[] = Bill::create([
                        'contract_id' => $contract->id,
                        'month_year' => $monthYearStr,
                        'amount_due' => $monthlyAmount,
                        'amount_paid' => 0,
                        'balance' => $monthlyAmount,
                        'status' => 'unpaid',
                        'due_date' => $dueDate->toDateString(),
                    ]);
                }

                // 4. Simulate Payments
                // Scenario:
                // 1. Fully paid up to current month
                // 2. Partially paid
                // 3. Late/Unpaid
                $scenario = rand(1, 3);
                $totalPaid = 0;

                if ($scenario == 1) {
                    // Fully paid for 4 months (Sept, Oct, Nov, Dec)
                    $monthsToPay = 4;
                } elseif ($scenario == 2) {
                    // Paid for 2 months, partial on the 3rd
                    $monthsToPay = 2.5;
                } else {
                    // Paid for only 1 month
                    $monthsToPay = 1;
                }

                $paymentTypes = ['cash', 'bank_transfer', 'cheque'];

                foreach ($bills as $index => $bill) {
                    if ($monthsToPay <= 0) break;

                    $paymentAmount = 0;
                    if ($monthsToPay >= 1) {
                        $paymentAmount = $bill->amount_due;
                        $monthsToPay -= 1;
                    } else {
                        $paymentAmount = $bill->amount_due * $monthsToPay;
                        $monthsToPay = 0;
                    }

                    if ($paymentAmount > 0) {
                        // Create Payment
                        $paidDate = Carbon::parse($bill->due_date)->subDays(rand(1, 4)); // Paid slightly before due date
                        $payment = Payment::create([
                            'contract_id' => $contract->id,
                            'amount' => $paymentAmount,
                            'payment_type' => $paymentTypes[array_rand($paymentTypes)],
                            'status' => 'paid',
                            'paid_date' => $paidDate,
                        ]);

                        // Allocate Payment
                        PaymentAllocation::create([
                            'payment_id' => $payment->id,
                            'bill_id' => $bill->id,
                            'amount' => $paymentAmount,
                        ]);

                        // Update Bill
                        $bill->amount_paid = $paymentAmount;
                        $bill->balance = $bill->amount_due - $paymentAmount;
                        if ($bill->balance == 0) {
                            $bill->status = 'paid';
                        } else {
                            $bill->status = 'partial';
                        }
                        $bill->save();

                        $totalPaid += $paymentAmount;
                    }
                }

                // Update bills that are overdue and unpaid
                foreach ($bills as $bill) {
                    if (Carbon::parse($bill->due_date)->isPast() && $bill->balance > 0) {
                        if ($bill->amount_paid > 0) {
                            $bill->status = 'partial'; // Keep it partial
                        } else {
                            $bill->status = 'late';
                        }
                        $bill->save();
                    }
                }

                // 5. Update Contract Balances
                $contract->paid_amount = $totalPaid;
                $contract->remaining_amount = $totalContractFees - $totalPaid;
                $contract->balance = $totalContractFees - $totalPaid;
                $contract->save();
            }
            DB::commit();
            $this->command->info('✅ Finances seed complete!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error during finances seeding: ' . $e->getMessage());
        }
    }
}
