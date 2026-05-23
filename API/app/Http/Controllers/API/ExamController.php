<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamExercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    /** GET /api/exams */
    public function index(Request $request)
    {
        $query = Exam::with(['subject', 'teacher', 'exercises', 'classes']);

        if ($request->filled('subject_id'))   $query->where('subject_id', $request->integer('subject_id'));
        if ($request->filled('teacher_id'))   $query->where('teacher_id', $request->integer('teacher_id'));
        if ($request->filled('semester'))     $query->where('semester', $request->input('semester'));
        if ($request->filled('academic_year'))$query->where('academic_year', $request->input('academic_year'));
        if ($request->filled('exam_type'))    $query->where('exam_type', $request->input('exam_type'));
        if ($request->filled('class_id')) {
            $query->whereHas('classes', fn($q) => $q->where('classes.id', $request->integer('class_id')));
        }

        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->get()]);
    }

    /** GET /api/exams/types */
    public function getTypes(Request $request)
    {
        $query = Exam::query();

        if ($request->filled('semester') && $request->input('semester') !== 'all') {
            $query->where('semester', $request->input('semester'));
        }
        if ($request->filled('academic_year') && $request->input('academic_year') !== 'all') {
            $query->where('academic_year', $request->input('academic_year'));
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }
        if ($request->filled('class_id')) {
            $classId = $request->integer('class_id');
            // Include exams linked to the class via the pivot table
            // OR via students that belong to that class (handles cases where pivot was not populated)
            $query->where(function ($q) use ($classId) {
                $q->whereHas('classes', fn($sq) => $sq->where('classes.id', $classId))
                  ->orWhereHas('grades.student', fn($sq) => $sq->where('class_id', $classId));
            });
        }
        if ($request->filled('student_id')) {
            $student = \App\Models\Student::find($request->integer('student_id'));
            if ($student && $student->class_id) {
                $query->where(function ($q) use ($student) {
                    $q->whereHas('classes', fn($sq) => $sq->where('classes.id', $student->class_id))
                      ->orWhereHas('grades.student', fn($sq) => $sq->where('class_id', $student->class_id));
                });
            }
        }

        $types = $query->distinct()->pluck('exam_type');

        return response()->json(['success' => true, 'data' => $types]);
    }

    /** POST /api/exams */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject_id'    => 'required|exists:subjects,id',
            'teacher_id'    => 'required|exists:teachers,id',
            'exam_type'     => 'required|string|max:255',
            'semester'      => 'required|string|max:255',
            'academic_year' => 'required|string|max:255',
            'max_grade'     => 'nullable|numeric|min:0',
            'class_ids'     => 'nullable|array',
            'class_ids.*'   => 'exists:classes,id',
            'exercises'     => 'nullable|array',
            'exercises.*.level_name' => 'required_with:exercises|string|max:255',
            'exercises.*.max_note'   => 'required_with:exercises|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $exam = Exam::create([
            'subject_id'    => $request->subject_id,
            'teacher_id'    => $request->teacher_id,
            'exam_type'     => $request->exam_type,
            'semester'      => $request->semester,
            'academic_year' => $request->academic_year,
            'max_grade'     => $request->input('max_grade', 20),
        ]);

        if ($request->filled('class_ids')) {
            $exam->classes()->sync($request->class_ids);
        }

        if ($request->filled('exercises')) {
            foreach ($request->exercises as $ex) {
                $exam->exercises()->create([
                    'level_name' => $ex['level_name'],
                    'max_note'   => $ex['max_note'],
                ]);
            }
        }

        $exam->load(['subject', 'teacher', 'exercises', 'classes']);

        return response()->json(['success' => true, 'data' => $exam], 201);
    }

    /** GET /api/exams/{exam} */
    public function show(Exam $exam)
    {
        $exam->load(['subject', 'teacher', 'exercises', 'classes']);
        return response()->json(['success' => true, 'data' => $exam]);
    }

    /** PUT /api/exams/{exam} */
    public function update(Request $request, Exam $exam)
    {
        $validator = Validator::make($request->all(), [
            'subject_id'    => 'sometimes|exists:subjects,id',
            'teacher_id'    => 'sometimes|exists:teachers,id',
            'exam_type'     => 'sometimes|string|max:255',
            'semester'      => 'sometimes|string|max:255',
            'academic_year' => 'sometimes|string|max:255',
            'max_grade'     => 'nullable|numeric|min:0',
            'class_ids'     => 'nullable|array',
            'class_ids.*'   => 'exists:classes,id',
            'exercises'     => 'nullable|array',
            'exercises.*.id' => 'nullable|exists:exam_exercises,id',
            'exercises.*.level_name' => 'required_with:exercises|string|max:255',
            'exercises.*.max_note'   => 'required_with:exercises|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $exam->update($request->only(['subject_id', 'teacher_id', 'exam_type', 'semester', 'academic_year', 'max_grade']));

        if ($request->has('class_ids')) {
            $exam->classes()->sync($request->class_ids ?? []);
        }

        if ($request->has('exercises')) {
            $exerciseData = $request->input('exercises', []);
            $keepIds = [];

            foreach ($exerciseData as $ex) {
                if (isset($ex['id'])) {
                    // Update existing
                    $exercise = $exam->exercises()->findOrFail($ex['id']);
                    $exercise->update([
                        'level_name' => $ex['level_name'],
                        'max_note'   => $ex['max_note']
                    ]);
                    $keepIds[] = $ex['id'];
                } else {
                    // Create new
                    $newEx = $exam->exercises()->create([
                        'level_name' => $ex['level_name'],
                        'max_note'   => $ex['max_note']
                    ]);
                    $keepIds[] = $newEx->id;
                }
            }

            // Remove exercises that were deleted in the UI
            $exam->exercises()->whereNotIn('id', $keepIds)->delete();
        }

        $exam->load(['subject', 'teacher', 'exercises', 'classes']);

        return response()->json(['success' => true, 'data' => $exam]);
    }

    /** DELETE /api/exams/{exam} */
    public function destroy(Exam $exam)
    {
        $exam->delete();
        return response()->json(['success' => true, 'message' => 'Exam deleted.']);
    }
}
