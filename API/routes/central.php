<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\TenantController;
use App\Http\Controllers\API\SuperAdminDashboardController;
use App\Http\Controllers\API\PlatformPaymentController;

// ── Public (no auth) ─────────────────────────────────────
Route::post('/login', [AuthController::class, 'centralLogin'])->middleware('throttle:5,1');

// ── Super Admin protected routes ─────────────────────────
Route::middleware(['auth:sanctum', 'superadmin'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard/stats', [SuperAdminDashboardController::class, 'index']);
    Route::get('/dashboard/revenue-chart', [SuperAdminDashboardController::class, 'revenueChart']);
    Route::get('/dashboard/tenant-growth', [SuperAdminDashboardController::class, 'tenantGrowth']);

    // Tenant Management
    Route::apiResource('tenants', TenantController::class);
    Route::post('/tenants/{id}/activate', [TenantController::class, 'activate']);
    Route::post('/tenants/{id}/deactivate', [TenantController::class, 'deactivate']);

    // Platform Payments
    Route::get('/payments/summary', [PlatformPaymentController::class, 'summary']);
    Route::apiResource('payments', PlatformPaymentController::class);

    // Activity Log
    Route::get('/activity-log', [TenantController::class, 'activityLog']);
});
