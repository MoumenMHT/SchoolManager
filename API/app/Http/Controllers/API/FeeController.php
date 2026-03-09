<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Fee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FeeController extends Controller
{
    /**
     * Phase 1.1: Display all fees with filters
     */
    public function index(Request $request)
    {
        try {
            $query = Fee::query();

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Filter by academic year
            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            // Filter by fee type/category if needed
            if ($request->has('fee_type')) {
                $query->where('name', 'like', '%' . $request->fee_type . '%');
            }

            // Sort by base_amount or name
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $fees = $query->get();

            return response()->json([
                'success' => true,
                'data' => $fees,
                'summary' => [
                    'total_fees' => $fees->count(),
                    'active_fees' => $fees->where('is_active', true)->count(),
                    'inactive_fees' => $fees->where('is_active', false)->count(),
                    'total_base_amount' => $fees->where('is_active', true)->sum('base_amount'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_fees'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Phase 1.1: Admin creates a new fee
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'description' => 'nullable|string|max:600',
                'base_amount' => 'required|numeric|min:0',
                'academic_year' => 'required|string|max:60',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $fee = Fee::create([
                'name' => $request->name,
                'description' => $request->description,
                'base_amount' => $request->base_amount,
                'academic_year' => $request->academic_year,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.fee_created'),
                'data' => $fee
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_create_fee'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified fee
     */
    public function show($id)
    {
        try {
            $fee = Fee::with('parentFees')->findOrFail($id);

            // Get usage statistics
            $usageStats = [
                'total_parents_using' => $fee->parentFees()->distinct('parent_id')->count(),
                'total_contracts_using' => DB::table('parents_fees')
                    ->join('contracts', 'parents_fees.parent_id', '=', 'contracts.parent_id')
                    ->where('parents_fees.fee_id', $id)
                    ->where('contracts.status', 'active')
                    ->distinct('contracts.id')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $fee,
                'usage_statistics' => $usageStats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.fee_not_found'),
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified fee
     */
    public function update(Request $request, $id)
    {
        try {
            $fee = Fee::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:100',
                'description' => 'nullable|string|max:600',
                'base_amount' => 'sometimes|numeric|min:0',
                'academic_year' => 'sometimes|string|max:60',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if fee is being used in active contracts
            $activeUsage = DB::table('parents_fees')
                ->join('contracts', 'parents_fees.parent_id', '=', 'contracts.parent_id')
                ->where('parents_fees.fee_id', $id)
                ->where('contracts.status', 'active')
                ->exists();

            // Warning if trying to modify amount on active contracts
            if ($request->has('base_amount') && $activeUsage && $request->base_amount != $fee->base_amount) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.cannot_modify_active_fee'),
                    'suggestion' => 'Create a new fee version for the next academic year instead.'
                ], 422);
            }

            $fee->update($request->all());

            return response()->json([
                'success' => true,
                'message' => __('messages.fee_updated'),
                'data' => $fee
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_update_fee'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete/Deactivate a fee
     */
    public function destroy($id)
    {
        try {
            $fee = Fee::findOrFail($id);

            // Check if fee is being used
            $isUsed = DB::table('parents_fees')
                ->where('fee_id', $id)
                ->exists();

            if ($isUsed) {
                // Don't delete, just deactivate
                $fee->is_active = false;
                $fee->save();

                return response()->json([
                    'success' => true,
                    'message' => __('messages.fee_deactivated'),
                    'data' => $fee
                ]);
            }

            // If not used, safe to delete
            $fee->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.fee_deleted')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_delete_fee'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle fee active status
     */
    public function toggleStatus($id)
    {
        try {
            $fee = Fee::findOrFail($id);
            $fee->is_active = !$fee->is_active;
            $fee->save();

            return response()->json([
                'success' => true,
                'message' => __('messages.fee_status_updated'),
                'data' => $fee
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_toggle_fee_status'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available fees for contract creation
     */
    public function availableForContract(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'academic_year' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $fees = Fee::where('is_active', true)
                ->where('academic_year', $request->academic_year)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => __('messages.available_fees'),
                'data' => $fees,
                'total_if_all_selected' => $fees->sum('base_amount')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_fees'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create fees (useful for new academic year setup)
     */
    public function bulkStore(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fees' => 'required|array|min:1',
                'fees.*.name' => 'required|string|max:100',
                'fees.*.description' => 'nullable|string|max:600',
                'fees.*.base_amount' => 'required|numeric|min:0',
                'academic_year' => 'required|string|max:60',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $createdFees = [];
            foreach ($request->fees as $feeData) {
                $fee = Fee::create([
                    'name' => $feeData['name'],
                    'description' => $feeData['description'] ?? null,
                    'base_amount' => $feeData['base_amount'],
                    'academic_year' => $request->academic_year,
                    'is_active' => true,
                ]);
                $createdFees[] = $fee;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdFees) . ' fees created successfully',
                'data' => $createdFees
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_create_fee'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Copy fees from one academic year to another
     */
    public function copyToNewYear(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'from_academic_year' => 'required|string',
                'to_academic_year' => 'required|string|different:from_academic_year',
                'increase_percentage' => 'nullable|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $copiedFees = [];
            $feesToCopy = Fee::where('academic_year', $request->from_academic_year)
                ->where('is_active', true)
                ->get();

            foreach ($feesToCopy as $fee) {
                $newFeeData = $fee->toArray();
                $newFeeData['academic_year'] = $request->to_academic_year;
                $newFeeData['base_amount'] = $request->has('increase_percentage')
                    ? $newFeeData['base_amount'] * (1 + $request->increase_percentage / 100)
                    : $newFeeData['base_amount'];
                unset($newFeeData['id']);

                $newFee = Fee::create($newFeeData);
                $copiedFees[] = $newFee;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($copiedFees) . ' fees copied successfully',
                'data' => $copiedFees
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_copy_fees'),
                'error' => $e->getMessage()
            ], 500);
        }catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_copy_fees'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fees statistics and summary
     */
    public function statistics(Request $request)
    {
        try {
            $query = Fee::query();

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            $fees = $query->get();

            $statistics = [
                'total_fees' => $fees->count(),
                'active_fees' => $fees->where('is_active', true)->count(),
                'inactive_fees' => $fees->where('is_active', false)->count(),
                'total_base_amount' => $fees->where('is_active', true)->sum('base_amount'),
                'average_fee_amount' => $fees->where('is_active', true)->avg('base_amount'),
                'highest_fee' => $fees->where('is_active', true)->max('base_amount'),
                'lowest_fee' => $fees->where('is_active', true)->min('base_amount'),
                'by_academic_year' => $fees->groupBy('academic_year')->map(function ($yearFees) {
                    return [
                        'count' => $yearFees->count(),
                        'total_amount' => $yearFees->sum('base_amount'),
                        'active' => $yearFees->where('is_active', true)->count(),
                    ];
                }),
                'usage_statistics' => DB::table('parents_fees')
                    ->select('fee_id', DB::raw('count(distinct parent_id) as parent_count'))
                    ->groupBy('fee_id')
                    ->get()
                    ->mapWithKeys(function ($item) use ($fees) {
                        $fee = $fees->firstWhere('id', $item->fee_id);
                        return [$fee?->name ?? 'Unknown' => $item->parent_count];
                    }),
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_fee_statistics'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
