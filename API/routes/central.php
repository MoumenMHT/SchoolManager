<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TenantController;
use App\Http\Controllers\API\AuthController;

Route::post('/login', [AuthController::class, 'centralLogin'])->middleware('throttle:5,1');

// Superadmin routes that do not require a tenant context
Route::middleware(['api', 'auth:sanctum'])->group(function () {
    // We'll protect this with a superadmin role/ability check later
    Route::apiResource('tenants', TenantController::class);
});
