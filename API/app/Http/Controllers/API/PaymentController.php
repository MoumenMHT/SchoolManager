<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Contract;
use App\Models\Bill;
use App\Models\PaymentAllocation;
use App\Models\Student;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Payment::with(['contract.parent', 'allocations.bill']);
            
            if ($request->has('contract_id')) {
                $query->where('contract_id', $request->contract_id);
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            $payments = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $payments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_payments'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created payment and allocate to bills
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contract_id' => 'required|exists:contracts,id',
                'amount' => 'required|numeric|min:0',
                'payment_type' => 'required|string',
                'paid_date' => 'required|date',
                'note' => 'nullable|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $contract = Contract::findOrFail($request->contract_id);

            // Create payment record
            $payment = Payment::create([
                'contract_id' => $request->contract_id,
                'amount' => $request->amount,
                'payment_type' => $request->payment_type,
                'paid_date' => $request->paid_date,
                'status' => 'completed',
                'note' => $request->note
            ]);

            // Allocate payment to unpaid bills
            $remainingAmount = $request->amount;
            $unpaidBills = Bill::where('contract_id', $contract->id)
                ->where('status', '!=', 'paid')
                ->orderBy('due_date', 'asc')
                ->get();

            foreach ($unpaidBills as $bill) {
                if ($remainingAmount <= 0) break;

                $billBalance = $bill->amount_due - $bill->amount_paid;
                $allocationAmount = min($remainingAmount, $billBalance);

                // Create allocation record
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'bill_id' => $bill->id,
                    'amount' => $allocationAmount
                ]);

                // Update bill
                $bill->amount_paid += $allocationAmount;
                $bill->updateStatus();

                $remainingAmount -= $allocationAmount;
            }

            // Update contract
            $contract->paid_amount += $request->amount;
            $contract->remaining_amount = max(0, $contract->remaining_amount - $request->amount);
            
            // Handle overpayment
            if ($remainingAmount > 0) {
                $contract->balance += $remainingAmount;
            }

            $contract->save();

            DB::commit();

            $payment->load(['contract', 'allocations.bill']);

            return response()->json([
                'success' => true,
                'message' => __('messages.payment_processed'),
                'data' => $payment,
                'overpayment' => $remainingAmount > 0 ? $remainingAmount : 0
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_process_payment'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        try {
            $payment = Payment::with(['contract', 'allocations.bill'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.payment_not_found'),
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get payment history for a contract
     */
    public function contractPayments($contractId)
    {
        try {
            $payments = Payment::with(['allocations.bill'])
                ->where('contract_id', $contractId)
                ->orderBy('paid_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_contract_payments'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $payment = Payment::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'payment_type' => 'sometimes|string',
                'status' => 'sometimes|in:completed,pending,cancelled,refunded',
                'note' => 'nullable|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $payment->update($request->all());
            $payment->load(['contract', 'allocations.bill']);
            
            return response()->json([
                'success' => true,
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_update_payment'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();

            $payment = Payment::with(['contract', 'allocations.bill'])->findOrFail($id);
            
            // Reverse all allocations
            foreach ($payment->allocations as $allocation) {
                $bill = $allocation->bill;
                $bill->amount_paid -= $allocation->amount;
                $bill->updateStatus();
            }

            // Update contract
            $contract = $payment->contract;
            $contract->paid_amount -= $payment->amount;
            $contract->remaining_amount += $payment->amount;
            
            // Adjust balance if there was overpayment
            if ($contract->balance > 0) {
                $contract->balance = max(0, $contract->balance - $payment->amount);
            }
            
            $contract->save();

            // Delete allocations and payment
            $payment->allocations()->delete();
            $payment->delete();

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => __('messages.payment_deleted')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_delete_payment'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment receipt with full details
     */
    public function receipt($id)
    {
        try {
            $payment = Payment::with([
                'contract.parent.user',
                'allocations.bill'
            ])->findOrFail($id);

            $receipt = [
                'payment_id' => $payment->id,
                'payment_date' => $payment->paid_date,
                'amount' => $payment->amount,
                'payment_type' => $payment->payment_type,
                'contract_number' => $payment->contract->contract_number,
                'parent_name' => $payment->contract->parent->user->name ?? 
                                ($payment->contract->parent->first_name . ' ' . $payment->contract->parent->last_name),
                'academic_year' => $payment->contract->academic_year,
                'allocated_to' => $payment->allocations->map(function ($allocation) {
                    return [
                        'bill_id' => $allocation->bill_id,
                        'month_year' => $allocation->bill->month_year,
                        'amount' => $allocation->amount,
                        'bill_status' => $allocation->bill->status
                    ];
                }),
                'note' => $payment->note
            ];

            return response()->json([
                'success' => true,
                'data' => $receipt
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_generate_receipt'),
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Process refund for a payment
     */
    public function refund(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'refund_amount' => 'required|numeric|min:0',
                'refund_reason' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $payment = Payment::with(['contract', 'allocations.bill'])->findOrFail($id);

            if ($request->refund_amount > $payment->amount) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.refund_exceeds_payment')
                ], 422);
            }

            // Create refund payment record
            $refundPayment = Payment::create([
                'contract_id' => $payment->contract_id,
                'amount' => -$request->refund_amount,
                'payment_type' => 'refund',
                'paid_date' => now(),
                'status' => 'refunded',
                'note' => 'Refund for payment #' . $payment->id . '. Reason: ' . $request->refund_reason
            ]);

            // Reverse allocations proportionally
            $refundRemaining = $request->refund_amount;
            foreach ($payment->allocations as $allocation) {
                if ($refundRemaining <= 0) break;

                $refundAllocationAmount = min($refundRemaining, $allocation->amount);

                // Create negative allocation
                PaymentAllocation::create([
                    'payment_id' => $refundPayment->id,
                    'bill_id' => $allocation->bill_id,
                    'amount' => -$refundAllocationAmount
                ]);

                // Update bill
                $bill = $allocation->bill;
                $bill->amount_paid -= $refundAllocationAmount;
                $bill->updateStatus();

                $refundRemaining -= $refundAllocationAmount;
            }

            // Update contract
            $contract = $payment->contract;
            $contract->paid_amount -= $request->refund_amount;
            $contract->remaining_amount += $request->refund_amount;
            
            // Adjust balance
            if ($contract->balance > 0) {
                $contract->balance = max(0, $contract->balance - $request->refund_amount);
            }
            
            $contract->save();

            // Mark original payment as refunded
            $payment->status = 'refunded';
            $payment->note = ($payment->note ?? '') . ' | Refunded: ' . $request->refund_amount . ' on ' . now();
            $payment->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('messages.payment_refunded'),
                'data' => [
                    'original_payment' => $payment,
                    'refund_payment' => $refundPayment
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_process_refund'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics for a contract
     */
    public function contractStatistics($contractId)
    {
        try {
            $contract = Contract::with(['bills', 'payments'])->findOrFail($contractId);

            $statistics = [
                'contract_number' => $contract->contract_number,
                'academic_year' => $contract->academic_year,
                'total_contract_amount' => $contract->total_fees - $contract->discount_value,
                'total_paid' => $contract->paid_amount,
                'total_remaining' => $contract->remaining_amount,
                'overpayment_balance' => $contract->balance,
                'monthly_amount' => $contract->monthly_amount,
                'bills_summary' => [
                    'total_bills' => $contract->bills->count(),
                    'paid_bills' => $contract->bills->where('status', 'paid')->count(),
                    'unpaid_bills' => $contract->bills->whereIn('status', ['unpaid', 'late'])->count(),
                    'partial_bills' => $contract->bills->where('status', 'partial')->count(),
                ],
                'payment_summary' => [
                    'total_payments' => $contract->payments->count(),
                    'total_payment_amount' => $contract->payments->sum('amount'),
                    'last_payment_date' => $contract->payments->max('paid_date'),
                ],
                'next_due_bill' => $contract->bills()
                    ->whereIn('status', ['unpaid', 'partial', 'late'])
                    ->orderBy('due_date', 'asc')
                    ->first(),
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_statistics'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history with detailed tracking
     */
    public function paymentHistory(Request $request, $contractId)
    {
        try {
            $query = Payment::with(['allocations.bill'])
                ->where('contract_id', $contractId);

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('paid_date', [$request->start_date, $request->end_date]);
            }

            if ($request->has('payment_type')) {
                $query->where('payment_type', $request->payment_type);
            }

            $payments = $query->orderBy('paid_date', 'desc')->get();

            $history = $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_type' => $payment->payment_type,
                    'paid_date' => $payment->paid_date,
                    'status' => $payment->status,
                    'note' => $payment->note,
                    'bills_paid' => $payment->allocations->map(function ($allocation) {
                        return [
                            'bill_id' => $allocation->bill_id,
                            'month_year' => $allocation->bill->month_year,
                            'amount_allocated' => $allocation->amount,
                            'bill_status_after' => $allocation->bill->status
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_payment_history'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get parent dashboard with payment overview
     */
    public function parentDashboard($parentId)
    {
        try {
            $contracts = Contract::with(['bills', 'payments'])
                ->where('parent_id', $parentId)
                ->where('status', 'active')
                ->get();

            $dashboard = $contracts->map(function ($contract) {
                return [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'academic_year' => $contract->academic_year,
                    'total_amount' => $contract->total_fees - $contract->discount_value,
                    'paid_amount' => $contract->paid_amount,
                    'remaining_amount' => $contract->remaining_amount,
                    'balance' => $contract->balance,
                    'monthly_amount' => $contract->monthly_amount,
                    'next_due_date' => $contract->bills()
                        ->whereIn('status', ['unpaid', 'partial', 'late'])
                        ->orderBy('due_date', 'asc')
                        ->first()?->due_date,
                    'unpaid_bills_count' => $contract->bills->whereIn('status', ['unpaid', 'late'])->count(),
                    'late_bills_count' => $contract->bills->where('status', 'late')->count(),
                    'last_payment' => $contract->payments->sortByDesc('paid_date')->first(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $dashboard
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_parent_dashboard'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get admin financial reports
     */
    public function financialReports(Request $request)
    {
        try {
            $query = Payment::with(['contract.parent.user']);

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('paid_date', [$request->start_date, $request->end_date]);
            }

            if ($request->has('academic_year')) {
                $query->whereHas('contract', function ($q) use ($request) {
                    $q->where('academic_year', $request->academic_year);
                });
            }

            $payments = $query->get();

            $report = [
                'total_payments' => $payments->count(),
                'total_amount_collected' => $payments->where('status', 'completed')->sum('amount'),
                'total_refunds' => $payments->where('payment_type', 'refund')->sum('amount'),
                'net_amount' => $payments->where('status', 'completed')->sum('amount') + $payments->where('payment_type', 'refund')->sum('amount'),
                'payment_by_type' => $payments->groupBy('payment_type')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total' => $group->sum('amount')
                    ];
                }),
                'contracts_summary' => Contract::with('parent.user')
                    ->where('status', 'active')
                    ->when($request->has('academic_year'), function ($q) use ($request) {
                        $q->where('academic_year', $request->academic_year);
                    })
                    ->get()
                    ->map(function ($contract) {
                        return [
                            'contract_number' => $contract->contract_number,
                            'parent_name' => $contract->parent->user->name ?? 
                                           ($contract->parent->first_name . ' ' . $contract->parent->last_name),
                            'total_fees' => $contract->total_fees - $contract->discount_value,
                            'paid_amount' => $contract->paid_amount,
                            'remaining_amount' => $contract->remaining_amount,
                            'payment_completion' => $contract->paid_amount > 0 
                                ? round(($contract->paid_amount / ($contract->total_fees - $contract->discount_value)) * 100, 2) 
                                : 0
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_generate_report'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate payment breakdown before processing
     */
    public function calculatePayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contract_id' => 'required|exists:contracts,id',
                'amount' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $contract = Contract::findOrFail($request->contract_id);
            $unpaidBills = Bill::where('contract_id', $contract->id)
                ->where('status', '!=', 'paid')
                ->orderBy('due_date', 'asc')
                ->get();

            $remainingAmount = $request->amount;
            $allocations = [];

            foreach ($unpaidBills as $bill) {
                if ($remainingAmount <= 0) break;

                $billBalance = $bill->amount_due - $bill->amount_paid;
                $allocationAmount = min($remainingAmount, $billBalance);

                $allocations[] = [
                    'bill_id' => $bill->id,
                    'month_year' => $bill->month_year,
                    'due_date' => $bill->due_date,
                    'current_balance' => $billBalance,
                    'amount_to_allocate' => $allocationAmount,
                    'remaining_balance' => $billBalance - $allocationAmount,
                    'new_status' => ($billBalance - $allocationAmount) <= 0 ? 'paid' : 'partial'
                ];

                $remainingAmount -= $allocationAmount;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_amount' => $request->amount,
                    'will_be_allocated' => $request->amount - $remainingAmount,
                    'overpayment' => $remainingAmount,
                    'allocations' => $allocations,
                    'contract_summary' => [
                        'current_paid' => $contract->paid_amount,
                        'after_payment_paid' => $contract->paid_amount + $request->amount,
                        'current_remaining' => $contract->remaining_amount,
                        'after_payment_remaining' => max(0, $contract->remaining_amount - $request->amount),
                        'current_balance' => $contract->balance,
                        'after_payment_balance' => $contract->balance + $remainingAmount
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_calculate_payment'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
