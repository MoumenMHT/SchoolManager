<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\ParentModel;


class ParentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $parents = ParentModel::withCount('students')->get();
        return response()->json([
            'success' => true,
            'data' => $parents
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'cin' => 'nullable|string|max:20|unique:parents,cin',
            'profession' => 'nullable|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

    if ($request->cin) {
            $existingParent = ParentModel::where($request->only('first_name', 'last_name'))->first();
            if ($existingParent) {
                return response()->json([
                    'success' => false,
                    'message' => 'A parent with the same first and last name already exists.'
                ], 409);
            }
        }

        $parent = ParentModel::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'cin' => $request->cin,
            'profession' => $request->profession,
        ]);

        return response()->json([
            'success' => true,
            'data' => $parent
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $parent = ParentModel::find($id);
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $parent
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $parent = ParentModel::find($id);
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'cin' => 'nullable|string|max:20|unique:parents,cin,' . $parent->id,
            'profession' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->cin) {
            $existingParent = ParentModel::where($request->only('first_name', 'last_name'))
                ->where('id', '!=', $parent->id)
                ->first();
            if ($existingParent) {
                return response()->json([
                    'success' => false,
                    'message' => 'A parent with the same first and last name already exists.'
                ], 409);
            }
        }

        $parent->update($request->only([
            'first_name',
            'last_name',
            'phone',
            'email',
            'cin',
            'profession'
        ]));

        return response()->json([
            'success' => true,
            'data' => $parent
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $parent = ParentModel::find($id);
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent not found'
            ], 404);
        }
        $parent->delete();
        return response()->json([
            'success' => true,
            'message' => 'Parent deleted successfully'
        ]);
    }

    /**
     * Create user account for parent
     */
    public function createAccount(Request $request, string $id)
    {
        $parent = ParentModel::find($id);
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent not found'
            ], 404);
        }

        if ($parent->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Parent already has an account'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Create user account
        $user = \App\Models\User::create([
            'username' => strtolower($parent->first_name . '_' . $parent->last_name),
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'parent',
            'phone' => $parent->phone,
            'is_active' => true,
        ]);

        // Link user to parent
        $parent->user_id = $user->id;
        $parent->save();
        $parent->loadCount('students');

        return response()->json([
            'success' => true,
            'message' => 'User account created successfully',
            'data' => $parent->load('user')
        ], 201);
    }
}
