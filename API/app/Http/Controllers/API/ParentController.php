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
        $query = ParentModel::withCount('students');

        $user = auth()->user();
        if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
            $directorCycle = $user->directorCycle();
            $query->whereHas('students.class.levelProfile', function($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }

        $parents = $query->get();
        $parents->load('user:id,email,phone'); // Load email and phone fields from the related user

         $parents->each(function ($parent) {
            $parent->email = $parent->user ? $parent->user->email : null; // Add email attribute to parent
            $parent->phone = $parent->user ? $parent->user->phone : null; // Add phone attribute to parent
            unset($parent->user); // Remove the user relationship to avoid confusion
        });
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
            'cin' => 'nullable|string|max:20',
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
                    'message' => __('messages.parent_name_exists')
                ], 409);
            }
        }
        if ($request->cin) {
            $existingParent = ParentModel::where('cin', $request->cin)->first();
            if ($existingParent) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.parent_cin_exists')
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
        $parent = ParentModel::with(['students.class'])->find($id);
        $parent->load('user:id,email,phone'); // Load email and phone fields from the related user

        $parent->email = $parent->user ? $parent->user->email : null; // Add email attribute to parent
        $parent->phone = $parent->user ? $parent->user->phone : null; // Add phone attribute to parent
        unset($parent->user); // Remove the user relationship to avoid confusion
        

        
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => __('messages.parent_not_found')
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
                'message' => __('messages.parent_not_found')
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
                    'message' => __('messages.parent_name_exists')
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
                'message' => __('messages.parent_not_found')
            ], 404);
        }
        $parent->delete();
        return response()->json([
            'success' => true,
            'message' => __('messages.parent_deleted')
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
                'message' => __('messages.parent_not_found')
            ], 404);
        }

        if ($parent->user_id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.parent_has_account')
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,username',
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
            'username' => $request->username,
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
            'message' => __('messages.account_created'),
            'data' => $parent->load('user')
        ], 201);
    }
}
