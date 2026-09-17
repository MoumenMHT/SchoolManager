<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantActive
{
    /**
     * Block all API requests for deactivated tenants.
     * The login endpoint returns a descriptive message so this middleware
     * only applies to already-authenticated, tenant-scoped operations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && !$tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('messages.tenant_inactive'),
                'tenant_inactive' => true,
            ], 403);
        }

        return $next($request);
    }
}
