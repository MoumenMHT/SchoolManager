<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    /**
     * List all tenants with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query();

        // Search by name (stored in JSON data column)
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('data->name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'inactive') {
                $query->inactive();
            }
        }

        // Filter by plan
        if ($plan = $request->get('plan')) {
            $query->byPlan($plan);
        }

        // Filter expiring soon
        if ($request->boolean('expiring_soon')) {
            $query->expiringSoon();
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['id', 'created_at', 'is_active', 'subscription_plan', 'subscription_expires_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $tenants = $query->paginate($perPage);

        // Attach counts to each tenant
        $tenants->getCollection()->transform(function (Tenant $tenant) {
            $tenant->setAttribute('users_count', $tenant->usersCount());
            $tenant->setAttribute('students_count', $tenant->studentsCount());
            return $tenant;
        });

        return response()->json(['success' => true, 'data' => $tenants]);
    }

    /**
     * Create a new tenant with optional first admin user.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'                  => 'required|string|max:64|unique:tenants,id|regex:/^[a-zA-Z0-9_\-]+$/',
            'name'                => 'required|string|max:255',
            'contact_name'        => 'nullable|string|max:255',
            'contact_email'       => 'nullable|email|max:255',
            'contact_phone'       => 'nullable|string|max:50',
            'subscription_plan'   => 'nullable|string|max:50',
            'subscription_expires_at' => 'nullable|date',
            'max_students'        => 'nullable|integer|min:0',
            'notes'               => 'nullable|string|max:2000',
            'address'             => 'nullable|string|max:500',
            'phone'               => 'nullable|string|max:50',
            'email'               => 'nullable|email|max:255',
            // Optional first admin user
            'admin_username'      => 'nullable|required_with:admin_password|string|max:255',
            'admin_password'      => 'nullable|required_with:admin_username|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request) {
            $tenant = Tenant::create([
                'id' => $request->id,
                'data' => [
                    'name'    => $request->name,
                    'address' => $request->address,
                    'phone'   => $request->phone,
                    'email'   => $request->email,
                ],
                'is_active'               => true,
                'last_activation_at'      => now(),
                'contact_name'            => $request->contact_name,
                'contact_email'           => $request->contact_email,
                'contact_phone'           => $request->contact_phone,
                'subscription_plan'       => $request->subscription_plan ?? 'basic',
                'subscription_expires_at' => $request->subscription_expires_at,
                'max_students'            => $request->max_students ?? 0,
                'notes'                   => $request->notes,
            ]);

            // Optionally create the first admin user for this tenant
            if ($request->filled('admin_username') && $request->filled('admin_password')) {
                $admin = new User();
                $admin->username = $request->admin_username;
                $admin->password = Hash::make($request->admin_password);
                $admin->role = 'admin';
                $admin->is_active = true;
                $admin->tenant_id = $tenant->id;
                $admin->save();
            }

            TenantActivityLog::log($tenant->id, 'created', [
                'name' => $request->name,
                'plan' => $tenant->subscription_plan,
                'admin_created' => $request->filled('admin_username'),
            ]);

            return response()->json(['success' => true, 'data' => $tenant], 201);
        });
    }

    /**
     * Get a single tenant with detailed stats.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $stats = [
            'users_count'    => $tenant->usersCount(),
            'students_count' => $tenant->studentsCount(),
            'teachers_count' => $tenant->teachersCount(),
            'total_payments' => $tenant->platformPayments()->completed()->sum('amount'),
            'pending_payments' => $tenant->platformPayments()->pending()->sum('amount'),
            'last_payment'   => $tenant->platformPayments()->completed()->latest('payment_date')->first(),
        ];

        $recentActivity = $tenant->activityLogs()
            ->with('performer:id,username')
            ->recent(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'stats' => $stats,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Update tenant details.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'                    => 'sometimes|string|max:255',
            'contact_name'            => 'nullable|string|max:255',
            'contact_email'           => 'nullable|email|max:255',
            'contact_phone'           => 'nullable|string|max:50',
            'subscription_plan'       => 'nullable|string|max:50',
            'subscription_expires_at' => 'nullable|date',
            'max_students'            => 'nullable|integer|min:0',
            'notes'                   => 'nullable|string|max:2000',
            'address'                 => 'nullable|string|max:500',
            'phone'                   => 'nullable|string|max:50',
            'email'                   => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $changes = [];

        // Update JSON data fields
        $dataFields = ['name', 'address', 'phone', 'email'];
        foreach ($dataFields as $field) {
            if ($request->has($field)) {
                $oldVal = $tenant->{$field};
                $tenant->{$field} = $request->{$field};
                if ($oldVal !== $request->{$field}) {
                    $changes[$field] = ['old' => $oldVal, 'new' => $request->{$field}];
                }
            }
        }

        // Update real columns
        $realFields = ['contact_name', 'contact_email', 'contact_phone', 'subscription_plan', 'subscription_expires_at', 'max_students', 'notes'];
        foreach ($realFields as $field) {
            if ($request->has($field)) {
                $oldVal = $tenant->{$field};
                $newVal = $request->{$field};
                if ((string) $oldVal !== (string) $newVal) {
                    $changes[$field] = ['old' => $oldVal, 'new' => $newVal];
                }
                $tenant->{$field} = $newVal;
            }
        }

        if ($request->filled('admin_password')) {
            $adminUser = User::where('tenant_id', $tenant->id)->where('role', 'admin')->first();
            if ($adminUser) {
                $adminUser->password = Hash::make($request->admin_password);
                $adminUser->save();
                $changes['admin_password'] = ['old' => '***', 'new' => '***'];
            }
        }

        $tenant->save();

        if (!empty($changes)) {
            TenantActivityLog::log($tenant->id, 'updated', $changes);
        }

        return response()->json(['success' => true, 'data' => $tenant]);
    }

    /**
     * Activate a tenant.
     */
    public function activate(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        if ($tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant is already active.',
            ], 422);
        }

        $tenant->activate();

        TenantActivityLog::log($tenant->id, 'activated');

        return response()->json(['success' => true, 'data' => $tenant]);
    }

    /**
     * Deactivate a tenant.
     */
    public function deactivate(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        if (!$tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant is already inactive.',
            ], 422);
        }

        $tenant->deactivate();

        TenantActivityLog::log($tenant->id, 'deactivated');

        return response()->json(['success' => true, 'data' => $tenant]);
    }

    /**
     * Delete a tenant (deactivate, never hard-delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $tenant->deactivate();

        TenantActivityLog::log($tenant->id, 'deleted_soft', [
            'name' => $tenant->name,
        ]);

        return response()->json(['success' => true, 'message' => 'Tenant has been deactivated.']);
    }

    /**
     * Get platform-wide activity log (all tenants).
     */
    public function activityLog(Request $request): JsonResponse
    {
        $query = TenantActivityLog::with(['tenant', 'performer:id,username']);

        if ($tenantId = $request->get('tenant_id')) {
            $query->forTenant($tenantId);
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($perPage),
        ]);
    }
}
