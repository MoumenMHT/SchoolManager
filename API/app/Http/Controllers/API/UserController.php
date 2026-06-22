<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Optional filtering by multiple roles passed as comma-separated string
        if ($request->has('roles')) {
            $roles = explode(',', $request->input('roles'));
            $query->whereIn('role', $roles);
        } elseif ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Return all users with their associated profile (teacher/supervisor/parent) for full-name display.
     */
    public function withProfile(Request $request)
    {
        $query = User::with(['teacher', 'supervisor', 'parent']);

        if ($request->has('roles')) {
            $roles = explode(',', $request->input('roles'));
            $query->whereIn('role', $roles);
        } elseif ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%")
                  ->orWhereHas('teacher', function ($q2) use ($search) {
                      $q2->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('supervisor', function ($q2) use ($search) {
                      $q2->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('parent', function ($q2) use ($search) {
                      $q2->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->input('paginate') === 'false') {
            $users = $query->orderBy('created_at', 'desc')->get();
            $paginated = null;
        } else {
            $perPage = $request->input('per_page', 15);
            $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);
            $users = collect($paginated->items());
        }

        $mappedUsers = $users->map(function ($user) {
            $profile = $user->teacher ?? $user->supervisor ?? $user->parent ?? null;
            $fullName = $profile
                ? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''))
                : null;

            return [
                'id'        => $user->id,
                'username'  => $user->username,
                'role'      => $user->role,
                'is_active' => $user->is_active,
                'full_name' => $fullName,
            ];
        });

        if ($paginated) {
            $paginated->setCollection($mappedUsers);
            return response()->json(['success' => true, 'data' => $paginated]);
        }

        return response()->json(['success' => true, 'data' => $mappedUsers]);
    }

    /**
     * Update only credentials (username / password) for the User Management page.
     */
    public function updateCredentials(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
        ]);

        $user->username = $validated['username'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return response()->json(['success' => true, 'message' => 'Credentials updated successfully']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:admin,teacher,parent,supervisor,secretariat,accountant,primary_director,cem_director,lycee_director',
            'is_active' => 'boolean',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->input('is_active', true);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|string|in:admin,teacher,parent,supervisor,secretariat,accountant,primary_director,cem_director,lycee_director',
            'is_active' => 'boolean',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        
        // Ensure admin cannot delete themselves
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot delete yourself.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
