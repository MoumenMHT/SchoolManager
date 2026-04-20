<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Subject;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = \App\Models\Subject::query();

        $user = auth()->user();
        if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
            $directorCycle = $user->directorCycle();
            $query->whereHas('levels', function($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }

        $subjects = $query->get();

        return response()->json([
            'success' => true,
            'data' => $subjects
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
            'name' => 'required|string|max:255|unique:subjects,name',
            'code' => 'required|string|max:20|unique:subjects,code',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->code) {
            $existingSubject = Subject::where($request->only('name'))->first();
            if ($existingSubject) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.subject_name_exists')
                ], 409);
            }
        }

        $subject = \App\Models\Subject::create($request->only('name', 'code', 'description'));

        return response()->json([
            'success' => true,
            'data' => $subject
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $subject = \App\Models\Subject::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $subject
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
        $subject = \App\Models\Subject::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:subjects,name',
            'code' => 'required|string|max:20|unique:subjects,code',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->code) {
            $existingSubject = Subject::where($request->only('name'))
                ->where('id', '!=', $subject->id)
                ->first();
            if ($existingSubject) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.subject_name_exists')
                ], 409);
            }
        }

         

        $subject->update($request->only('name', 'code', 'description'));

         return response()->json([
            'success' => true,
            'data' => $subject
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $subject = \App\Models\Subject::findOrFail($id);
        $subject->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.subject_deleted')
        ]);
    }
}
