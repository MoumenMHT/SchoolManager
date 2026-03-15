<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\Contract;

class BillController extends Controller
{
    /**
     * Get all bills for a contract
     */
    public function index(Request $request)
    {
        try {
            $query = Bill::with(['contract', 'paymentAllocations.payment']);

            // Parents can only see bills for their own contracts
            if ($request->user()->role === 'parent') {
                $parent = $request->user()->parent;
                if (!$parent) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.parent_profile_not_found')
                    ], 404);
                }
                $query->whereHas('contract', function ($q) use ($parent) {
                    $q->where('parent_id', $parent->id);
                });
            } else {
                if ($request->has('contract_id')) {
                    $query->where('contract_id', $request->contract_id);
                }
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $bills = $query->orderBy('due_date', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $bills
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_bills'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get specific bill details
     */
    public function show($id)
    {
        try {
            $bill = Bill::with(['contract', 'paymentAllocations.payment'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $bill
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.bill_not_found'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Get unpaid bills for a contract
     */
    public function unpaid($contractId)
    {
        try {
            $bills = Bill::where('contract_id', $contractId)
                ->whereIn('status', ['unpaid', 'partial', 'late'])
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bills
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_bills'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
