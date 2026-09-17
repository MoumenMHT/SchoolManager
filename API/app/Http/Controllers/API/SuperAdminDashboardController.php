<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PlatformPayment;
use App\Models\Tenant;
use App\Models\TenantActivityLog;
use App\Models\User;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboardController extends Controller
{
    /**
     * Get all platform-wide KPIs.
     */
    public function index(): JsonResponse
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::active()->count();
        $inactiveTenants = Tenant::inactive()->count();
        $expiringSoon = Tenant::expiringSoon(30)->count();

        // Revenue
        $totalRevenue = PlatformPayment::completed()->sum('amount');
        $revenueThisMonth = PlatformPayment::completed()
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $revenueLastMonth = PlatformPayment::completed()
            ->whereMonth('payment_date', now()->subMonth()->month)
            ->whereYear('payment_date', now()->subMonth()->year)
            ->sum('amount');
        $revenueChange = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100 : 0);

        $pendingPayments = PlatformPayment::pending()->sum('amount');

        // Cross-tenant aggregate counts (no tenant scope for superadmin)
        $totalStudents = Student::withoutGlobalScopes()->count();
        $totalUsers = User::withoutGlobalScopes()->where('role', '!=', 'superadmin')->count();

        // Plan breakdown
        $planBreakdown = Tenant::select('subscription_plan', DB::raw('count(*) as count'))
            ->groupBy('subscription_plan')
            ->pluck('count', 'subscription_plan');

        // Payment status breakdown
        $paymentStatusBreakdown = PlatformPayment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Recent activity
        $recentActivity = TenantActivityLog::with(['tenant', 'performer:id,username'])
            ->recent(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tenants' => [
                    'total' => $totalTenants,
                    'active' => $activeTenants,
                    'inactive' => $inactiveTenants,
                    'expiring_soon' => $expiringSoon,
                ],
                'revenue' => [
                    'total' => round($totalRevenue, 2),
                    'this_month' => round($revenueThisMonth, 2),
                    'last_month' => round($revenueLastMonth, 2),
                    'change_percent' => $revenueChange,
                    'pending' => round($pendingPayments, 2),
                ],
                'platform' => [
                    'total_students' => $totalStudents,
                    'total_users' => $totalUsers,
                ],
                'plan_breakdown' => $planBreakdown,
                'payment_status_breakdown' => $paymentStatusBreakdown,
                'recent_activity' => $recentActivity,
            ],
        ]);
    }

    /**
     * Monthly revenue data for chart (last 12 months).
     */
    public function revenueChart(): JsonResponse
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $amount = PlatformPayment::completed()
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('amount');
            $months->push([
                'month' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'amount' => round((float) $amount, 2),
            ]);
        }

        return response()->json(['success' => true, 'data' => $months]);
    }

    /**
     * Tenant registration/growth over time (last 12 months).
     */
    public function tenantGrowth(): JsonResponse
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Tenant::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $months->push([
                'month' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'count' => $count,
            ]);
        }

        return response()->json(['success' => true, 'data' => $months]);
    }
}
