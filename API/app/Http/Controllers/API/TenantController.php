<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        // Require superadmin role for tenant management
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $tenants = \App\Models\Tenant::all();
        return response()->json(['success' => true, 'data' => $tenants]);
    }

    public function store(\Illuminate\Http\Request $request)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'id' => 'required|string|unique:tenants,id|regex:/^[a-zA-Z0-9\-]+$/',
        ]);

        $tenant = \App\Models\Tenant::create([
            'id' => $validated['id'],
            'data' => [
                'name' => $request->name ?? $validated['id'],
            ]
        ]);

        return response()->json(['success' => true, 'data' => $tenant], 201);
    }

    public function show($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $tenant = \App\Models\Tenant::findOrFail($id);
        return response()->json(['success' => true, 'data' => $tenant]);
    }

    public function destroy($id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $tenant = \App\Models\Tenant::findOrFail($id);
        $tenant->delete();
        
        return response()->json(['success' => true, 'message' => 'Tenant deleted successfully']);
    }
}
