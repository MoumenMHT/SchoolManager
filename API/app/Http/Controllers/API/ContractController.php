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
     * Create a new contract with auto-generated bills
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'parent_id' => 'required|exists:parents,id',
                'fee_ids' => 'required|array|min:1',
                'fee_ids.*' => 'exists:fees,id',
                'academic_year' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'discount_type' => 'nullable|string',
                'discount_value' => 'nullable|numeric|min:0',
                'discount_reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Calculate total fees
            $fees = Fee::whereIn('id', $request->fee_ids)->get();
            $totalFees = $fees->sum('base_amount');

            // Apply discount
            $discountValue = $request->discount_value ?? 0;
            if ($request->discount_type === 'percentage') {
                $discountValue = ($totalFees * $discountValue) / 100;
            }
            $finalAmount = $totalFees - $discountValue;

            // Calculate months between start and end date
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $monthsDiff = $startDate->diffInMonths($endDate) + 1;
            $monthlyAmount = $finalAmount / $monthsDiff;

            // Create contract
            $contract = Contract::create([
                'parent_id' => $request->parent_id,
                'academic_year' => $request->academic_year,
                'total_fees' => $totalFees,
                'discount_type' => $request->discount_type,
                'discount_value' => $request->discount_value ?? 0,
                'discount_reason' => $request->discount_reason,
                'monthly_amount' => $monthlyAmount,
                'paid_amount' => 0,
                'remaining_amount' => $finalAmount,
                'balance' => 0,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'notes' => $request->notes,
                'status' => 'active',
            ]);

            // Create parent_fees records
            foreach ($request->fee_ids as $feeId) {
                ParentFee::create([
                    'parent_id' => $request->parent_id,
                    'fee_id' => $feeId,
                ]);
            }

            // Auto-generate monthly bills
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                Bill::create([
                    'contract_id' => $contract->id,
                    'month_year' => $currentDate->format('F Y'),
                    'amount_due' => $monthlyAmount,
                    'amount_paid' => 0,
                    'balance' => $monthlyAmount,
                    'status' => 'unpaid',
                    'due_date' => $currentDate->copy()->endOfMonth(),
                ]);
                $currentDate->addMonth();
            }

            DB::commit();

            $contract->load(['parent', 'bills']);

            return response()->json([
                'success' => true,
                'message' => __('messages.contract_created'),
                'data' => $contract
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_create_contract'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get contract details
     */
    public function show(Request $request, $id)
    {
        try {
            $contract = Contract::with(['parent', 'bills', 'payments.allocations'])
                ->findOrFail($id);

            // Parents can only view their own contracts
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
                'data' => $contract
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.contract_not_found'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Get all contracts with filters
     */
    public function index(Request $request)
    {
        try {
            $query = Contract::with(['parent', 'bills']);

            // Parents can only see their own contracts
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

            $contracts = $query->get();

            return response()->json([
                'success' => true,
                'data' => $contracts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_contracts'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
