<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminOnly
{
    /**
     * Restrict access to superadmin users only.
     * Applied to central/platform routes that manage tenants, payments, etc.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthenticated'),
            ], 401);
        }

        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized'),
            ], 403);
        }

        return $next($request);
    }
}
