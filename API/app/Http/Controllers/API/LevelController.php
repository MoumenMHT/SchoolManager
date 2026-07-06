<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LevelController extends Controller
{
    /**
     * Get all levels
     */
    public function index(): JsonResponse
    {
        $query = Level::query();

        $user = auth()->user();
        $isAdminOrSecretariat = $user && in_array($user->role, ['admin', 'secretariat']);
        if (!$isAdminOrSecretariat && $user && method_exists($user, 'isDirector') && $user->isDirector()) {
            $directorCycle = $user->directorCycle();
            if ($directorCycle) {
                $query->where('cycle', $directorCycle);
            }
        }

        $levels = $query->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => $levels,
        ]);
    }

    /**
     * Get subjects for a specific level
     */
    public function subjects(Level $level): JsonResponse
    {
        $subjects = $level->subjects()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cycle' => ['required', 'string', 'max:255'],
            'year_number' => [
                'required', 
                'integer', 
                'min:1', 
                'max:10',
                Rule::unique('levels')->where(function ($query) use ($request) {
                    return $query->where('cycle', $request->cycle)
                                 ->where('track', $request->track);
                }),
            ],
            'track' => ['nullable', 'string'],
            'name' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('levels')->where(function ($query) use ($request) {
                    return $query->where('cycle', $request->cycle);
                }),
            ],
            'sort_order' => ['required', 'integer'],
            'is_active' => ['boolean'],
        ], [
            'year_number.unique' => __('messages.level_already_exists', [], 'en') ?? 'A level with this year and track already exists in this cycle.'
        ]);

        $level = Level::create($validated);

        return response()->json([
            'success' => true,
            'data' => $level,
        ], 201);
    }

    public function update(Request $request, Level $level): JsonResponse
    {
        $cycle = $request->input('cycle', $level->cycle);
        $track = $request->input('track', $level->track);

        $validated = $request->validate([
            'cycle' => ['sometimes', 'required', 'string', 'max:255'],
            'year_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:10',
                Rule::unique('levels')->where(function ($query) use ($cycle, $track) {
                    return $query->where('cycle', $cycle)
                                 ->where('track', $track);
                })->ignore($level->id),
            ],
            'track' => ['nullable', 'string'],
            'name' => [
                'sometimes', 
                'required', 
                'string', 
                'max:255',
                Rule::unique('levels')->where(function ($query) use ($cycle) {
                    return $query->where('cycle', $cycle);
                })->ignore($level->id),
            ],
            'sort_order' => ['sometimes', 'required', 'integer'],
            'is_active' => ['boolean'],
        ]);

        $level->update($validated);

        return response()->json([
            'success' => true,
            'data' => $level,
        ]);
    }

    public function destroy(Level $level): JsonResponse
    {
        if ($level->classes()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.cannot_delete_level_with_classes', [], 'en'),
            ], 400);
        }
        $level->delete();
        return response()->json(['success' => true]);
    }

    public function assignSubjects(Request $request, Level $level): JsonResponse
    {
        $validated = $request->validate([
            'subjects' => 'required|array',
            'subjects.*.subject_id' => 'required|exists:subjects,id',
            'subjects.*.coefficient' => 'required|integer|min:1',
            // Accept both field names: frontend sends `weekly_hours`, DB column is `weekly_sessions_required`
            'subjects.*.weekly_hours' => 'nullable|integer|min:1',
            'subjects.*.weekly_sessions_required' => 'nullable|integer|min:1',
        ]);

        $syncData = [];
        foreach ($validated['subjects'] as $subject) {
            // Accept weekly_hours (frontend name) or weekly_sessions_required (DB column name)
            $weeklySessions = $subject['weekly_hours'] ?? $subject['weekly_sessions_required'] ?? null;

            $syncData[$subject['subject_id']] = [
                'coefficient' => $subject['coefficient'],
                'weekly_sessions_required' => $weeklySessions,
            ];
        }

        $level->subjects()->sync($syncData);

        return response()->json(['success' => true]);
    }
}
