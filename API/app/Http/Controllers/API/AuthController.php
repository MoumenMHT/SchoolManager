<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Teacher;
use App\Models\ParentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Central Login for frontend. Finds the user ignoring tenant scope,
     * logs them in, and returns the tenant_id so frontend can proceed.
     */
    public function centralLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required_without:phone|string|nullable',
            'phone' => 'required_without:username|string|nullable',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user across ALL tenants
        $users = User::withoutGlobalScope(\Stancl\Tenancy\Database\Models\BelongsToTenant::class)
            ->with(['teacher', 'parent', 'parent.students', 'supervisor'])
            ->where(function($query) use ($request) {
                if ($request->username) {
                    $query->where('username', $request->username);
                }
                if ($request->phone) {
                    $query->orWhere('phone', $request->phone);
                }
            })->get();

        $matchedUser = null;
        foreach ($users as $user) {
            if (Hash::check($request->password, $user->password)) {
                $matchedUser = $user;
                break;
            }
        }

        if (!$matchedUser) {
            return response()->json([
                'success' => false, 
                'message' => __('messages.invalid_credentials')
            ], 401);
        }

        if (!$matchedUser->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('messages.account_inactive')
            ], 403);
        }

        $expiresAt = match($matchedUser->role) {
            'parent' => now()->addDays(30),
            'teacher' => now()->addDays(7),
            default => now()->addHours(24),
        };

        \Illuminate\Support\Facades\Auth::guard('web')->login($matchedUser, true);

        $userData = $matchedUser->toArray();
        if ($matchedUser->role === 'parent' && $matchedUser->parent) {
            $userData['students'] = $matchedUser->parent->students;
        }

        return response()->json([
            'success' => true,
            'tenant_id' => $matchedUser->tenant_id,
            'user' => $userData
        ]);
    }

    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required_without:phone|string|nullable',
            'phone' => 'required_without:username|string|nullable',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::with(['teacher', 'parent', 'parent.students', 'supervisor'])
            ->where(function($query) use ($request) {
            if ($request->username) {
                $query->where('username', $request->username);
            }
            if ($request->phone) {
                $query->orWhere('phone', $request->phone);
            }
        })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false, 
                'message' => __('messages.invalid_credentials')
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('messages.account_inactive')
            ], 403);
        }

        \Illuminate\Support\Facades\Auth::guard('web')->login($user, true);

        // Load related data based on role
        $userData = $user->toArray();
        if ($user->role === 'teacher' && $user->teacher) {
            $userData['teacher'] = $user->teacher;
        } elseif ($user->role === 'parent' && $user->parent) {
            $userData['parent'] = $user->parent;
            $userData['parent']['students'] = $user->parent->students;
        } elseif ($user->role === 'supervisor' && $user->supervisor) {
            $userData['supervisor'] = $user->supervisor;
        }

        return response()->json([
            'success' => true,
            'user' => $userData
        ]);
    }

    /**
     * Register new user (admin only)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('users')->where(function ($query) {
                    return $query->where('tenant_id', tenant('id'));
                })
            ],
            'password' => ['required', Password::min(8)],
            'role' => 'required|in:admin,teacher,parent',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'teacher_id' => 'required_if:role,teacher|exists:teachers,id',
            'parent_id' => 'required_if:role,parent|exists:parents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if teacher/parent already has an account
        if ($request->role === 'teacher') {
            $teacher = Teacher::find($request->teacher_id);
            if ($teacher->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.teacher_has_account')
                ], 409);
            }
        } elseif ($request->role === 'parent') {
            $parent = ParentModel::find($request->parent_id);
            if ($parent->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.parent_has_account_new')
                ], 409);
            }
        }

        $user = User::forceCreate([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => true,
        ]);

        // Link user account to existing teacher/parent record and return the updated record
        if ($user->role === 'teacher') {
            $teacher = Teacher::find($request->teacher_id);
            $teacher->update(['user_id' => $user->id]);
            
            return response()->json([
                'success' => true,
                'message' => __('messages.user_registered'),
                'data' => $teacher->fresh()
            ], 201);
        } elseif ($user->role === 'parent') {
            $parent = ParentModel::find($request->parent_id);
            $parent->update(['user_id' => $user->id]);
            
            return response()->json([
                'success' => true,
                'message' => __('messages.user_registered'),
                'data' => $parent->fresh()
            ], 201);
        }

        // For admin role, return user object
        return response()->json([
            'success' => true,
            'message' => __('messages.user_registered'),
            'user' => $user
        ], 201);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }
        }
        
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.logged_out')
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['teacher', 'parent', 'parent.students', 'supervisor']);
        
        // Load related data based on role
        $userData = $user->toArray();
        if ($user->role === 'teacher' && $user->teacher) {
            $userData['teacher'] = $user->teacher;
        } elseif ($user->role === 'parent' && $user->parent) {
            $userData['parent'] = $user->parent;
            $userData['parent']['students'] = $user->parent->students;
        } elseif ($user->role === 'supervisor' && $user->supervisor) {
            $userData['supervisor'] = $user->supervisor;
        }

        return response()->json([
            'success' => true,
            'user' => $userData
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.wrong_current_password')
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.password_changed')
        ]);
    }
}
