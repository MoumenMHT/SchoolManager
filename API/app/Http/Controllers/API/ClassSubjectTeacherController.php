<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\SubjectCoefficient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ClassSubjectTeacherController extends Controller
{
    /**
     * Get all class-subject-teacher assignments
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClassSubjectTeacher::with(['class', 'subject', 'teacher']);

        // Filter by academic year if provided
        if ($request->has('academic_year')) {
            $query->forAcademicYear($request->academic_year);
        }

        // Filter by class if provided
        if ($request->has('class_id')) {
            $query->forClass($request->class_id);
        }

        // Filter by teacher if provided
        if ($request->has('teacher_id')) {
            $query->forTeacher($request->teacher_id);
        }

        // Filter by subject if provided
        if ($request->has('subject_id')) {
            $query->forSubject($request->subject_id);
        }

        $assignments = $query->get();

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * Get assignments for a specific class
     */
    public function getClassAssignments($classId): JsonResponse
    {
        $class = SchoolClass::findOrFail($classId);
        $assignments = ClassSubjectTeacher::with(['subject', 'teacher'])
            ->where('class_id', $classId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'class' => $class,
                'assignments' => $assignments,
            ],
        ]);
    }

    /**
     * Get available teachers for a subject (teachers who can teach that subject)
     */
    public function getAvailableTeachers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:classes,id',
            'academic_year' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $subject = Subject::findOrFail($validated['subject_id']);
        $class = SchoolClass::findOrFail($validated['class_id']);
        
        // Get teachers who can teach this subject
        $qualifiedTeachers = $subject->qualifiedTeachers;

        // Check which ones are already assigned to this class for this subject
        $assignedTeacherIds = ClassSubjectTeacher::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('academic_year', $validated['academic_year'])
            ->pluck('teacher_id')
            ->toArray();

        // Get coefficient for this subject and class level
        $coefficient = SubjectCoefficient::getCoefficient($validated['subject_id'], $class->level);

        return response()->json([
            'success' => true,
            'data' => [
                'available_teachers' => $qualifiedTeachers,
                'assigned_teacher_ids' => $assignedTeacherIds,
                'coefficient' => $coefficient,
                'class_level' => $class->level,
            ],
        ]);
    }

    /**
     * Assign a teacher to a class for a subject (coefficient auto-fills)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'academic_year' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Validate that teacher can teach this subject
        $teacher = Teacher::findOrFail($validated['teacher_id']);
        if (!$teacher->canTeachSubject($validated['subject_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'This teacher is not qualified to teach this subject',
            ], 422);
        }

        // Validate that class is active
        $class = SchoolClass::findOrFail($validated['class_id']);
        if (!$class->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot assign teacher to an inactive class',
             ], 422);
        }

        //check if the class already has a teacher assigned for this subject and academic year
        $existingAssignment = ClassSubjectTeacher::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('academic_year', $validated['academic_year'])
            ->first();
        if ($existingAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'This class already has a teacher assigned for this subject and academic year',
            ], 422);
        }

        // Check if assignment already exists
        $exists = ClassSubjectTeacher::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('teacher_id', $validated['teacher_id'])
            ->where('academic_year', $validated['academic_year'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This assignment already exists',
            ], 422);
        }

        // Get coefficient from configuration
        $class = SchoolClass::findOrFail($validated['class_id']);
        $coefficient = SubjectCoefficient::getCoefficient($validated['subject_id'], $class->level);

        if (!$coefficient) {
            return response()->json([
                'success' => false,
                'message' => "No coefficient configured for this subject at level {$class->level}",
            ], 422);
        }

        // Create assignment with auto-filled coefficient
        $assignment = ClassSubjectTeacher::create([
            'class_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'teacher_id' => $validated['teacher_id'],
            'academic_year' => $validated['academic_year'],
            'coefficient' => $coefficient,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Teacher assigned successfully',
            'data' => $assignment->load(['class', 'subject', 'teacher']),
        ], 201);
    }

    /**
     * Update an assignment (only academic year can be changed, coefficient stays frozen)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $assignment = ClassSubjectTeacher::findOrFail($id);

        $validated = $request->validate([
            'academic_year' => 'required|string',
        ]);

        $assignment->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated successfully',
            'data' => $assignment->load(['class', 'subject', 'teacher']),
        ]);
    }

    /**
     * Remove a teacher assignment
     */
    public function destroy($id): JsonResponse
    {
        $assignment = ClassSubjectTeacher::findOrFail($id);
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment removed successfully',
        ]);
    }

    /**
     * Get coefficient preview for assignment
     */
    public function getCoefficientPreview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $class = SchoolClass::findOrFail($validated['class_id']);
        $coefficient = SubjectCoefficient::getCoefficient($validated['subject_id'], $class->level);

        return response()->json([
            'success' => true,
            'data' => [
                'class_level' => $class->level,
                'coefficient' => $coefficient,
                'configured' => $coefficient !== null,
            ],
        ]);
    }
}
