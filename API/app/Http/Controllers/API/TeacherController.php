<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Teacher;
use Illuminate\Support\Facades\Validator;


class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teachers = Teacher::all();
        return response()->json([
            'success' => true,
            'data' => $teachers
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'hire_date' => 'required|date',
            'specialization' => 'required|string|max:255',
            'salary' => 'required|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $teacher = Teacher::where($request->only('first_name', 'last_name'))->first();

        if ($teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher with the same first and last name already exists.'
            ], 409);
        }

        $teacher = Teacher::create([
            'user_id' => auth()->id(),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'birth_date' => $request->birth_date,
            'hire_date' => $request->hire_date,
            'specialization' => $request->specialization,
            'salary' => $request->salary,
        ]);

        return response()->json([
            'success' => true,
            'data' => $teacher
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $teacher = Teacher::find($id);
        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found.'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $teacher
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
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'birth_date' => 'sometimes|required|date',
            'hire_date' => 'sometimes|required|date',
            'specialization' => 'sometimes|required|string|max:255',
            'salary' => 'sometimes|required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found.'
            ], 404);
        }

        $teacher->update($request->only([
            'first_name', 
            'last_name', 
            'birth_date', 
            'hire_date', 
            'specialization', 
            'salary'
        ]));

        return response()->json([
            'success' => true,
            'data' => $teacher
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found.'
            ], 404);
        }

        $teacher->delete();

        return response()->json([
            'success' => true,
            'message' => 'Teacher deleted successfully.'
        ]);
    }
}
