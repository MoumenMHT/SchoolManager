<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\LevelSubject;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;


class SubjectCoefficientController extends Controller
{
    private function resolveLevelId(array $validated): ?int
    {
        if (!empty($validated['level_id'])) {
            return (int) $validated['level_id'];
        }

        if (!empty($validated['class_level'])) {
            return Level::where('name', $validated['class_level'])->value('id');
        }

        return null;
    }

    /**
     * Get all subject coefficients
     */
    public function index(): JsonResponse
    {
        $coefficients = LevelSubject::with(['subject', 'level'])->get();

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
        $coefficients = $subject->coefficients()->with('level')->get();

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
            'level_id' => 'nullable|exists:levels,id',
            'class_level' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $levelId = $this->resolveLevelId($validated);

        if (!$levelId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_failed'),
                'errors' => [
                    'level' => ['Either level_id or class_level is required and must exist.']
                ]
            ], 422);
        }

        $coefficient = LevelSubject::with(['subject', 'level'])
            ->where('subject_id', $validated['subject_id'])
            ->where('level_id', $levelId)
            ->first();

        if (!$coefficient) {
            return response()->json([
                'success' => false,
                'message' => __('messages.coefficient_not_found'),
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
            'level_id' => 'nullable|exists:levels,id',
            'class_level' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $levelId = $this->resolveLevelId($validated);

        if (!$levelId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_failed'),
                'errors' => [
                    'level' => ['Either level_id or class_level is required and must exist.']
                ]
            ], 422);
        }

        $coefficient = LevelSubject::with(['subject', 'level'])
            ->where('level_id', $levelId)
            ->get();

        if ($coefficient->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.coefficient_not_found'),
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
            'level_id' => 'nullable|exists:levels,id',
            'class_level' => 'nullable|string|max:100',
            'coefficient' => 'required|integer|min:1|max:10',
            'weekly_sessions_required' => 'required|integer|min:1|max:20',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $levelId = $this->resolveLevelId($validated);

        if (!$levelId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_failed'),
                'errors' => [
                    'level' => ['Either level_id or class_level is required and must exist.']
                ]
            ], 422);
        }

        // Check if coefficient already exists for this subject and level
        $exists = LevelSubject::where('subject_id', $validated['subject_id'])
            ->where('level_id', $levelId)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => __('messages.coefficient_already_exists'),
            ], 422);
        }

        $coefficient = LevelSubject::create([
            'subject_id' => $validated['subject_id'],
            'level_id' => $levelId,
            'coefficient' => $validated['coefficient'],
            'weekly_sessions_required' => $validated['weekly_sessions_required'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.coefficient_created'),
            'data' => $coefficient->load(['subject', 'level']),
        ], 201);
    }

    /**
     * Update a coefficient configuration
     */
    public function update(Request $request, $id): JsonResponse
    {
        $coefficient = LevelSubject::findOrFail($id);

        $validated = $request->validate([
            'coefficient' => 'sometimes|required|integer|min:1|max:10',
            'weekly_sessions_required' => 'sometimes|required|integer|min:1|max:20',
        ]);

        $coefficient->update($validated);

        return response()->json([
            'success' => true,
            'message' => __('messages.coefficient_updated'),
            'data' => $coefficient->load(['subject', 'level']),
        ]);
    }

    /**
     * Delete a coefficient configuration
     */
    public function destroy($id): JsonResponse
    {
        $coefficient = LevelSubject::findOrFail($id);
        $coefficient->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.coefficient_deleted'),
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
            'levels.*.level_id' => 'nullable|exists:levels,id',
            'levels.*.class_level' => 'nullable|string|max:100',
            'levels.*.coefficient' => 'required|integer|min:1|max:10',
            'levels.*.weekly_sessions_required' => 'required|integer|min:1|max:20',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $validated = $validator->validated();

        $created = [];
        $errors = [];

        // Pre-fetch class levels
        $classLevels = collect($validated['levels'])->pluck('class_level')->filter()->unique();
        $levelIdsByName = \App\Models\Level::whereIn('name', $classLevels)->pluck('id', 'name');

        $levelIdsToProcess = [];
        $levelDataMap = [];

        foreach ($validated['levels'] as $level) {
            $levelId = null;
            if (!empty($level['level_id'])) {
                $levelId = (int) $level['level_id'];
            } elseif (!empty($level['class_level'])) {
                $levelId = $levelIdsByName[$level['class_level']] ?? null;
            }

            if (!$levelId) {
                $errors[] = 'Invalid level entry in bulk payload.';
                continue;
            }

            $levelIdsToProcess[] = $levelId;
            $levelDataMap[$levelId] = $level;
        }

        $existingLevelSubjects = LevelSubject::where('subject_id', $validated['subject_id'])
            ->whereIn('level_id', $levelIdsToProcess)
            ->pluck('level_id')
            ->toArray();

        $insertData = [];
        foreach ($levelIdsToProcess as $levelId) {
            if (!in_array($levelId, $existingLevelSubjects)) {
                $level = $levelDataMap[$levelId];
                $insertData[] = [
                    'subject_id' => $validated['subject_id'],
                    'level_id' => $levelId,
                    'coefficient' => $level['coefficient'],
                    'weekly_sessions_required' => $level['weekly_sessions_required'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                // For the response $created array
                $created[] = [
                    'subject_id' => $validated['subject_id'],
                    'level_id' => $levelId,
                    'coefficient' => $level['coefficient'],
                    'weekly_sessions_required' => $level['weekly_sessions_required'],
                ];
            } else {
                $errors[] = 'Coefficient already exists for one of the provided levels.';
            }
        }

        if (!empty($insertData)) {
            LevelSubject::insert($insertData);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' coefficient(s) created successfully',
            'data' => $created,
            'errors' => $errors,
        ], 201);
    }
}
