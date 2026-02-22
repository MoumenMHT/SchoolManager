<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubjectCoefficient;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;


class SubjectCoefficientController extends Controller
{
    /**
     * Get all subject coefficients
     */
    public function index(): JsonResponse
    {
        $coefficients = SubjectCoefficient::with('subject')->get();

        return response()->json([
            'success' => true,
            'data' => $coefficients,
        ]);
    }

    /**
     * Get coefficients for a specific subject
     */
    public function getSubjectCoefficients($subjectId): JsonResponse
    {
        $subject = Subject::findOrFail($subjectId);
        $coefficients = $subject->coefficients;

        return response()->json([
            'success' => true,
            'data' => $coefficients,
        ]);
    }

    /**
     * Get coefficient for a specific subject and class level
     */
    public function getCoefficient(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'class_level' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $coefficient = SubjectCoefficient::where('subject_id', $validated['subject_id'])
            ->where('class_level', $validated['class_level'])
            ->first();

        if (!$coefficient) {
            return response()->json([
                'success' => false,
                'message' => 'No coefficient configured for this subject and level',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $coefficient,
        ]);
    }
    public function getCoefficientByclassLevel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'class_level' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $coefficient = SubjectCoefficient::where('class_level', $validated['class_level'])  
            ->get();

        if ($coefficient->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No coefficient configured for this level',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $coefficient,
        ]);
    }

    /**
     * Create a new coefficient configuration
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'class_level' => 'required|string|max:100',
            'coefficient' => 'required|integer|min:1|max:10',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        // Check if coefficient already exists for this subject and level
        $exists = SubjectCoefficient::where('subject_id', $validated['subject_id'])
            ->where('class_level', $validated['class_level'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Coefficient already configured for this subject and level',
            ], 422);
        }

        $coefficient = SubjectCoefficient::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coefficient created successfully',
            'data' => $coefficient->load('subject'),
        ], 201);
    }

    /**
     * Update a coefficient configuration
     */
    public function update(Request $request, $id): JsonResponse
    {
        $coefficient = SubjectCoefficient::findOrFail($id);

        $validated = $request->validate([
            'coefficient' => 'required|integer|min:1|max:10',
        ]);

        $coefficient->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coefficient updated successfully',
            'data' => $coefficient->load('subject'),
        ]);
    }

    /**
     * Delete a coefficient configuration
     */
    public function destroy($id): JsonResponse
    {
        $coefficient = SubjectCoefficient::findOrFail($id);
        $coefficient->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coefficient deleted successfully',
        ]);
    }

    /**
     * Bulk create coefficients for a subject across multiple levels
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'levels' => 'required|array',
            'levels.*.class_level' => 'required|string|max:100',
            'levels.*.coefficient' => 'required|integer|min:1|max:10',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $validated = $validator->validated();

        $created = [];
        $errors = [];

        foreach ($validated['levels'] as $level) {
            $exists = SubjectCoefficient::where('subject_id', $validated['subject_id'])
                ->where('class_level', $level['class_level'])
                ->exists();

            if (!$exists) {
                $created[] = SubjectCoefficient::create([
                    'subject_id' => $validated['subject_id'],
                    'class_level' => $level['class_level'],
                    'coefficient' => $level['coefficient'],
                ]);
            } else {
                $errors[] = "Coefficient already exists for level: {$level['class_level']}";
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' coefficient(s) created successfully',
            'data' => $created,
            'errors' => $errors,
        ], 201);
    }
}
