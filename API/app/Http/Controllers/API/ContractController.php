<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contract;
use App\Models\ParentFee;
use App\Models\Fee;
use App\Models\Bill;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContractController extends Controller
{
    /**
     * Create a new contract with per-student fee assignment and auto-generated bills.
     *
     * Payload structure:
     * {
     *   parent_id: number,
     *   student_fees: [{ student_id: number, fee_ids: number[] }, ...],
     *   academic_year: string,
     *   start_date: string,
     *   end_date: string,
     *   discount_type?: string|null,
     *   discount_value?: number,
     *   discount_reason?: string,
     *   notes?: string,
     *   is_active?: boolean
     * }
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'parent_id'               => 'required|exists:parents,id',
                'student_fees'            => 'required|array|min:1',
                'student_fees.*.student_id' => 'required|exists:students,id',
                'student_fees.*.fee_ids'  => 'required|array|min:1',
                'student_fees.*.fee_ids.*'=> 'exists:fees,id',
                'academic_year'           => 'required|string',
                'start_date'              => 'required|date',
                'end_date'                => 'required|date|after:start_date',
                'discount_type'           => 'nullable|string',
                'discount_value'          => 'nullable|numeric|min:0',
                'discount_reason'         => 'nullable|string',
                'notes'                   => 'nullable|string',
                'is_active'               => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors'  => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Collect all unique fee ids across students to compute total
            $allFeeIds = collect($request->student_fees)
                ->flatMap(fn($sf) => $sf['fee_ids'])
                ->unique()
                ->values();

            $allFees = Fee::whereIn('id', $allFeeIds)->get()->keyBy('id');

            // But total is the SUM of each student's fees (same fee on 2 students counts twice)
            $totalFees = 0;
            foreach ($request->student_fees as $sf) {
                $totalFees += collect($sf['fee_ids'])->map(fn($id) => $allFees[$id]->base_amount ?? 0)->sum();
            }

            // Apply discount
            $discountValue = $request->discount_value ?? 0;
            if ($request->discount_type === 'percentage') {
                $discountValue = ($totalFees * $discountValue) / 100;
            }
            $finalAmount = max(0, $totalFees - $discountValue);

            // Monthly split
            $startDate   = Carbon::parse($request->start_date);
            $endDate     = Carbon::parse($request->end_date);
            $monthsDiff  = $startDate->diffInMonths($endDate) + 1;
            $monthlyAmount = $monthsDiff > 0 ? $finalAmount / $monthsDiff : $finalAmount;

            // Create contract
            $contract = Contract::create([
                'parent_id'        => $request->parent_id,
                'academic_year'    => $request->academic_year,
                'total_fees'       => $totalFees,
                'discount_type'    => $request->discount_type,
                'discount_value'   => $request->discount_value ?? 0,
                'discount_reason'  => $request->discount_reason,
                'monthly_amount'   => $monthlyAmount,
                'paid_amount'      => 0,
                'remaining_amount' => $finalAmount,
                'balance'          => 0,
                'start_date'       => $request->start_date,
                'end_date'         => $request->end_date,
                'notes'            => $request->notes,
                'status'           => 'active',
                'is_active'        => $request->is_active ?? true,
            ]);

            // Insert parents_fees rows with student_id
            foreach ($request->student_fees as $sf) {
                foreach ($sf['fee_ids'] as $feeId) {
                    ParentFee::firstOrCreate([
                        'parent_id'  => $request->parent_id,
                        'student_id' => $sf['student_id'],
                        'fee_id'     => $feeId,
                    ]);
                }
            }

            // Auto-generate monthly bills
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                Bill::create([
                    'contract_id' => $contract->id,
                    'month_year'  => $currentDate->format('F Y'),
                    'amount_due'  => $monthlyAmount,
                    'amount_paid' => 0,
                    'balance'     => $monthlyAmount,
                    'status'      => 'unpaid',
                    'due_date'    => $currentDate->copy()->endOfMonth(),
                ]);
                $currentDate->addMonth();
            }

            DB::commit();

            $contract->load(['parent', 'bills']);

            return response()->json([
                'success' => true,
                'message' => __('messages.contract_created'),
                'data'    => $contract
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_create_contract'),
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update an existing contract and re-sync per-student fees.
     */
    public function update(Request $request, $id)
    {
        try {
            $contract = Contract::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'student_fees'              => 'required|array|min:1',
                'student_fees.*.student_id' => 'required|exists:students,id',
                'student_fees.*.fee_ids'    => 'required|array|min:1',
                'student_fees.*.fee_ids.*'  => 'exists:fees,id',
                'academic_year'             => 'required|string',
                'start_date'                => 'required|date',
                'end_date'                  => 'required|date|after:start_date',
                'discount_type'             => 'nullable|string',
                'discount_value'            => 'nullable|numeric|min:0',
                'discount_reason'           => 'nullable|string',
                'notes'                     => 'nullable|string',
                'is_active'                 => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors'  => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $allFeeIds = collect($request->student_fees)
                ->flatMap(fn($sf) => $sf['fee_ids'])
                ->unique()
                ->values();

            $allFees = Fee::whereIn('id', $allFeeIds)->get()->keyBy('id');

            // Recalculate total (sum per student)
            $totalFees = 0;
            foreach ($request->student_fees as $sf) {
                $totalFees += collect($sf['fee_ids'])->map(fn($id) => $allFees[$id]->base_amount ?? 0)->sum();
            }

            $discountValue = $request->discount_value ?? 0;
            if ($request->discount_type === 'percentage') {
                $discountValue = ($totalFees * $discountValue) / 100;
            }
            $finalAmount     = max(0, $totalFees - $discountValue);
            $startDate       = Carbon::parse($request->start_date);
            $endDate         = Carbon::parse($request->end_date);
            $monthsDiff      = $startDate->diffInMonths($endDate) + 1;
            $monthlyAmount   = $monthsDiff > 0 ? $finalAmount / $monthsDiff : $finalAmount;
            $remainingAmount = max(0, $finalAmount - $contract->paid_amount);

            $contract->update([
                'academic_year'    => $request->academic_year,
                'total_fees'       => $totalFees,
                'discount_type'    => $request->discount_type,
                'discount_value'   => $request->discount_value ?? 0,
                'discount_reason'  => $request->discount_reason,
                'monthly_amount'   => $monthlyAmount,
                'remaining_amount' => $remainingAmount,
                'start_date'       => $request->start_date,
                'end_date'         => $request->end_date,
                'notes'            => $request->notes,
                'is_active'        => $request->is_active ?? $contract->is_active,
            ]);

            // Re-sync student fees: delete all student-level rows for this parent, then re-insert
            ParentFee::where('parent_id', $contract->parent_id)
                ->whereNotNull('student_id')
                ->delete();

            foreach ($request->student_fees as $sf) {
                foreach ($sf['fee_ids'] as $feeId) {
                    ParentFee::firstOrCreate([
                        'parent_id'  => $contract->parent_id,
                        'student_id' => $sf['student_id'],
                        'fee_id'     => $feeId,
                    ]);
                }
            }

            // Regenerate unpaid bills
            Bill::where('contract_id', $contract->id)->where('status', 'unpaid')->delete();

            $currentDate   = $startDate->copy();
            $existingBills = Bill::where('contract_id', $contract->id)->pluck('month_year')->toArray();

            while ($currentDate <= $endDate) {
                $monthYear = $currentDate->format('F Y');
                if (!in_array($monthYear, $existingBills)) {
                    Bill::create([
                        'contract_id' => $contract->id,
                        'month_year'  => $monthYear,
                        'amount_due'  => $monthlyAmount,
                        'amount_paid' => 0,
                        'balance'     => $monthlyAmount,
                        'status'      => 'unpaid',
                        'due_date'    => $currentDate->copy()->endOfMonth(),
                    ]);
                }
                $currentDate->addMonth();
            }

            DB::commit();

            $contract->load(['parent.studentFees.fee', 'parent.studentFees.student', 'bills']);

            return response()->json([
                'success' => true,
                'message' => __('messages.contract_updated', ['default' => 'Contract updated successfully']),
                'data'    => $contract
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_update_contract', ['default' => 'Failed to update contract']),
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get contract details
     */
    public function show(Request $request, $id)
    {
        try {
            $contract = Contract::with(['parent.studentFees.fee', 'parent.studentFees.student', 'bills', 'payments.allocations'])
                ->findOrFail($id);

            if ($request->user()->role === 'parent') {
                $parent = $request->user()->parent;
                if (!$parent || $contract->parent_id !== $parent->id) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.unauthorized')
                    ], 403);
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $contract
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.contract_not_found'),
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Get all contracts with filters
     */
    public function index(Request $request)
    {
        try {
            $query = Contract::with(['parent.studentFees.fee', 'parent.studentFees.student', 'bills']);

            if ($request->user()->role === 'parent') {
                $parent = $request->user()->parent;
                if (!$parent) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.parent_profile_not_found')
                    ], 404);
                }
                $query->where('parent_id', $parent->id);
            } else {
                if ($request->has('parent_id')) {
                    $query->where('parent_id', $request->parent_id);
                }
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('contract_number', 'like', "%{$search}%")
                      ->orWhereHas('parent', function($q2) use ($search) {
                          $q2->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->input('paginate') === 'false') {
                $contracts = $query->get();
            } else {
                $perPage = $request->input('per_page', 15);
                $contracts = $query->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'data'    => $contracts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_contracts'),
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
