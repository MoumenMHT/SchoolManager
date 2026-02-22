<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TeacherSubject;
use App\Models\Teacher;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TeacherSubjectController extends Controller
{
    /**
     * Get all teacher-subject assignments
     */
    public function index(): JsonResponse
    {
        $teacherSubjects = TeacherSubject::with(['teacher', 'subject'])->get();

        return response()->json([
            'success' => true,
            'data' => $teacherSubjects,
        ]);
    }

    /**
     * Get subjects for a specific teacher
     */
    public function getTeacherSubjects($teacherId): JsonResponse
    {
        $teacher = Teacher::findOrFail($teacherId);
        $subjects = $teacher->teachableSubjects;

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }

    /**
     * Get teachers for a specific subject
     */
    public function getSubjectTeachers($subjectId): JsonResponse
    {
        $subject = Subject::findOrFail($subjectId);
        $teachers = $subject->qualifiedTeachers;

        return response()->json([
            'success' => true,
            'data' => $teachers,
        ]);
    }

    /**
     * Assign a subject to a teacher
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }   

        $validated = $validator->validated();

        // Check if assignment already exists
        $exists = TeacherSubject::where('teacher_id', $validated['teacher_id'])
            ->where('subject_id', $validated['subject_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher is already assigned to this subject',
            ], 422);
        }

        $teacherSubject = TeacherSubject::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Subject assigned to teacher successfully',
            'data' => $teacherSubject->load(['teacher', 'subject']),
        ], 201);
    }

    /**
     * Assign multiple subjects to a teacher
     */
    public function assignMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $teacher = Teacher::findOrFail($validated['teacher_id']);
        $teacher->teachableSubjects()->syncWithoutDetaching($validated['subject_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Subjects assigned to teacher successfully',
            'data' => $teacher->teachableSubjects,
        ]);
    }

    /**
     * Remove a subject from a teacher
     */
    public function destroy($id): JsonResponse
    {
        $teacherSubject = TeacherSubject::findOrFail($id);
        $teacherSubject->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subject removed from teacher successfully',
        ]);
    }

    /**
     * Remove a specific subject from a specific teacher
     */
    public function removeSubjectFromTeacher(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $deleted = TeacherSubject::where('teacher_id', $validated['teacher_id'])
            ->where('subject_id', $validated['subject_id'])
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subject removed from teacher successfully',
        ]);
    }
}
