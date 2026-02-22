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

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:phone|email|nullable',
            'phone' => 'required_without:email|string|nullable',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where(function($query) use ($request) {
            if ($request->email) {
                $query->where('email', $request->email);
            }
            if ($request->phone) {
                $query->orWhere('phone', $request->phone);
            }
        })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false, 
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive'
            ], 403);
        }

        // Set token expiration based on role
        $expiresAt = match($user->role) {
            'admin' => now()->addHours(8),      // 8 hours for admins
            'teacher' => now()->addDays(7),     // 7 days for teachers
            'parent' => now()->addDays(7),      // 7 days for parents
            default => now()->addDay(),         // 24 hours default
        };

        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        // Load related data based on role
        $userData = $user->toArray();
        if ($user->role === 'teacher' && $user->teacher) {
            $userData['teacher'] = $user->teacher;
        } elseif ($user->role === 'parent' && $user->parent) {
            $userData['parent'] = $user->parent;
            $userData['parent']['students'] = $user->parent->students;
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $userData
        ]);
    }

    /**
     * Register new user (admin only)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,teacher,parent',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        // Load related data based on role
        $userData = $user->toArray();
        if ($user->role === 'teacher' && $user->teacher) {
            $userData['teacher'] = $user->teacher;
        } elseif ($user->role === 'parent' && $user->parent) {
            $userData['parent'] = $user->parent;
            $userData['parent']['students'] = $user->parent->students;
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
            'new_password' => 'required|min:8|confirmed',
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
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }
}
