<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PlatformPayment;
use App\Models\Tenant;
use App\Models\TenantActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlatformPaymentController extends Controller
{
    /**
     * List all platform payments with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PlatformPayment::with(['tenant', 'recordedBy:id,username']);

        if ($tenantId = $request->get('tenant_id')) {
            $query->forTenant($tenantId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $query->inDateRange($request->get('from'), $request->get('to'));

        if ($method = $request->get('payment_method')) {
            $query->where('payment_method', $method);
        }

        $sortBy = $request->get('sort_by', 'payment_date');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['payment_date', 'amount', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    /**
     * Record a new platform payment for a tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id'      => 'required|string|exists:tenants,id',
            'amount'         => 'required|numeric|min:0.01',
            'currency'       => 'nullable|string|size:3',
            'status'         => 'required|in:pending,completed,failed,refunded',
            'payment_method' => 'required|in:cash,bank_transfer,check,online',
            'payment_date'   => 'required|date',
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after_or_equal:period_start',
            'reference'      => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $payment = PlatformPayment::create([
            ...$validator->validated(),
            'recorded_by' => auth()->id(),
        ]);

        TenantActivityLog::log($request->tenant_id, 'payment_recorded', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'method' => $payment->payment_method,
        ]);

        $payment->load(['tenant', 'recordedBy:id,username']);

        return response()->json(['success' => true, 'data' => $payment], 201);
    }

    /**
     * Get a single payment.
     */
    public function show(int $id): JsonResponse
    {
        $payment = PlatformPayment::with(['tenant', 'recordedBy:id,username'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $payment]);
    }

    /**
     * Update a payment (status change, add notes, etc.).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $payment = PlatformPayment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'amount'         => 'sometimes|numeric|min:0.01',
            'status'         => 'sometimes|in:pending,completed,failed,refunded',
            'payment_method' => 'sometimes|in:cash,bank_transfer,check,online',
            'payment_date'   => 'sometimes|date',
            'period_start'   => 'sometimes|date',
            'period_end'     => 'sometimes|date',
            'reference'      => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldStatus = $payment->status;
        $payment->update($validator->validated());

        if ($oldStatus !== $payment->status) {
            TenantActivityLog::log($payment->tenant_id, 'payment_status_changed', [
                'payment_id' => $payment->id,
                'old_status' => $oldStatus,
                'new_status' => $payment->status,
            ]);
        }

        $payment->load(['tenant', 'recordedBy:id,username']);

        return response()->json(['success' => true, 'data' => $payment]);
    }

    /**
     * Delete a payment record.
     */
    public function destroy(int $id): JsonResponse
    {
        $payment = PlatformPayment::findOrFail($id);

        TenantActivityLog::log($payment->tenant_id, 'payment_deleted', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);

        $payment->delete();

        return response()->json(['success' => true, 'message' => 'Payment deleted successfully.']);
    }

    /**
     * Aggregated payment summary for dashboard cards.
     */
    public function summary(): JsonResponse
    {
        $totalCollected = PlatformPayment::completed()->sum('amount');
        $totalPending = PlatformPayment::pending()->sum('amount');

        $thisMonth = PlatformPayment::completed()
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $byMethod = PlatformPayment::completed()
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        return response()->json([
            'success' => true,
            'data' => [
                'total_collected' => round((float) $totalCollected, 2),
                'total_pending' => round((float) $totalPending, 2),
                'this_month' => round((float) $thisMonth, 2),
                'by_method' => $byMethod,
            ],
        ]);
    }
}
