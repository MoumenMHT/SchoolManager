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

            if ($request->has('contract_id')) {
                $query->where('contract_id', $request->contract_id);
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
                'message' => 'Failed to retrieve bills',
                'error' => $e->getMessage()
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
                'message' => 'Bill not found',
                'error' => $e->getMessage()
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
                'message' => 'Failed to retrieve unpaid bills',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
