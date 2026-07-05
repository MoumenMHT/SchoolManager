<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\LevelSubject;
use App\Services\GradingService;


class GradeController extends Controller
{
    private function applyGradeFilters(Builder $query, Request $request): Builder
    {
        $user = $request->user();
        if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
            $directorCycle = $user->directorCycle();
            $query->whereHas('student.class.levelProfile', function ($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }

        if ($request->filled('student_id')) {
            $query->where('grades.student_id', $request->integer('student_id'));
        }

        if ($request->filled('subject_id')) {
            $query->whereHas('exam', fn($q) => $q->where('subject_id', $request->integer('subject_id')));
        }

        if ($request->filled('teacher_id')) {
            $query->whereHas('exam', fn($q) => $q->where('teacher_id', $request->integer('teacher_id')));
        }

        if ($request->filled('semester')) {
            $query->whereHas('exam', fn($q) => $q->where('semester', $request->input('semester')));
        }

        if ($request->filled('academic_year')) {
            $query->whereHas('exam', fn($q) => $q->where('academic_year', $request->input('academic_year')));
        }

        if ($request->filled('exam_type')) {
            $query->whereHas('exam', fn($q) => $q->where('exam_type', $request->input('exam_type')));
        }

        if ($request->filled('class_id')) {
            $classId = $request->integer('class_id');
            $query->whereHas('student', fn($q) => $q->where('class_id', $classId));
        }

        return $query;
    }

    /**
     * Display a listing of the resource.
     * Can be filtered by student_id, subject_id, semester, academic_year
     * GET /api/grades?student_id={id}&subject_id={id}&semester={semester}
     */
    public function index(Request $request)
    {
        $query = Grade::with(['student', 'exam.subject', 'exam.teacher', 'exerciseGrades.exercise']);
        $this->applyGradeFilters($query, $request);

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = max(1, min(500, $request->integer('per_page', 100)));
            $grades = $query->orderByDesc('created_at')->paginate($perPage);
        } else {
            $grades = $query->get();
        }
        
        return response()->json([
            'success' => true,
            'data' => $grades,
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
            'student_id'       => 'required|exists:students,id',
            'exam_id'          => 'required|exists:exams,id',
            'grade'            => 'required|numeric|min:0',
            'comment'          => 'nullable|string',
            'exercise_grades'  => 'nullable|array',
            'exercise_grades.*.exam_exercise_id' => 'required_with:exercise_grades|exists:exam_exercises,id',
            'exercise_grades.*.note'             => 'required_with:exercise_grades|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $exam = Exam::find($request->exam_id);
        if ($request->grade > $exam->max_grade) {
            return response()->json(['success' => false, 'message' => __('messages.grade_exceeds_max')], 422);
        }

        $grade = Grade::create([
            'student_id' => $request->student_id,
            'exam_id'    => $request->exam_id,
            'grade'      => $request->grade,
            'comment'    => $request->comment,
        ]);

        if ($request->filled('exercise_grades')) {
            foreach ($request->exercise_grades as $eg) {
                $grade->exerciseGrades()->create([
                    'exam_exercise_id' => $eg['exam_exercise_id'],
                    'note'             => $eg['note'],
                ]);
            }
        }

        $grade->load(['student', 'exam.subject', 'exam.teacher', 'exerciseGrades.exercise']);

        return response()->json([
            'success' => true,
            'data'    => $grade,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $grade = Grade::with(['student', 'exam.subject', 'exam.teacher', 'exerciseGrades.exercise'])->find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => __('messages.grade_not_found')
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $grade,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $grade = Grade::find($id);
        
        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => __('messages.grade_not_found')
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'exam_id'  => 'sometimes|required|exists:exams,id',
            'grade'    => 'sometimes|required|numeric|min:0',
            'comment'  => 'nullable|string',
            'exercise_grades'  => 'nullable|array',
            'exercise_grades.*.exam_exercise_id' => 'required_with:exercise_grades|exists:exam_exercises,id',
            'exercise_grades.*.note'             => 'required_with:exercise_grades|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $grade->update($request->only(['exam_id', 'grade', 'comment']));

        if ($request->filled('exercise_grades')) {
            $grade->exerciseGrades()->delete();
            foreach ($request->exercise_grades as $eg) {
                $grade->exerciseGrades()->create([
                    'exam_exercise_id' => $eg['exam_exercise_id'],
                    'note'             => $eg['note'],
                ]);
            }
        }

        $grade->load(['student', 'exam.subject', 'exam.teacher', 'exerciseGrades.exercise']);

        return response()->json([
            'success' => true,
            'data' => $grade,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $grade = Grade::find($id);
        
        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => __('messages.grade_not_found')
            ], 404);
        }

        $grade->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.grade_deleted')
        ]);
    }

    /**
     * Get grades for a specific student
     * GET /api/students/{student_id}/grades
     */
    public function getStudentGrades(string $studentId, Request $request)
    {
        $student = Student::find($studentId);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_not_found')
            ], 404);
        }

        // Parents can only access grades for their own children
        if ($request->user()->role === 'parent') {
            $parent = $request->user()->parent;
            if (!$parent || $student->parent_id !== $parent->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthorized')
                ], 403);
            }
        }

        $query = Grade::with(['exam.subject', 'exam.teacher', 'exerciseGrades.exercise'])
            ->where('student_id', $studentId);

        if ($request->has('semester')) {
            $query->whereHas('exam', fn($q) => $q->where('semester', $request->semester));
        }
        if ($request->has('academic_year')) {
            $query->whereHas('exam', fn($q) => $q->where('academic_year', $request->academic_year));
        }
        if ($request->has('subject_id')) {
            $query->whereHas('exam', fn($q) => $q->where('subject_id', $request->subject_id));
        }

        $grades = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $student,
                'grades'  => $grades,
            ]
        ]);
    }

    /**
     * Get report card for a student with calculated averages
     * GET /api/students/{student_id}/report-card?semester={semester}&academic_year={year}
     */
    public function getStudentReportCard(string $studentId, Request $request)
    {
        $student = Student::with('class.levelProfile')->find($studentId);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_not_found')
            ], 404);
        }

        if ($request->user()->role === 'parent') {
            $parent = $request->user()->parent;
            if (!$parent || $student->parent_id !== $parent->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthorized')
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'semester' => 'required|string',
            'academic_year' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $averages = \App\Models\StudentAverage::with('subject')
            ->where('student_id', $studentId)
            ->where('trimester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->get();

        $rawGrades = \App\Models\Grade::with('exam.teacher')
            ->where('student_id', $studentId)
            ->whereHas('exam', fn($q) => $q
                ->where('semester', $request->semester)
                ->where('academic_year', $request->academic_year)
            )
            ->get();
        $gradesBySubject = $rawGrades->groupBy(fn($g) => $g->exam?->subject_id);

        $subjectAverages = $averages->where('record_type', 'subject')->values();
        $overallAverageRow = $averages->where('record_type', 'overall')->first();

        $levelId = $student->class->level_id;

        $subjectIds = $subjectAverages->pluck('subject_id')->unique();
        $coefficients = LevelSubject::where('level_id', $levelId)
            ->whereIn('subject_id', $subjectIds)
            ->pluck('coefficient', 'subject_id');

        $formattedSubjects = $subjectAverages->map(function ($avg) use ($levelId, $gradesBySubject, $coefficients) {
            $coefficient = $coefficients[$avg->subject_id] ?? 1;
            $subjectRawGrades = $gradesBySubject->get($avg->subject_id, collect());
            
            $cc          = $subjectRawGrades->first(fn($g) => $g->exam?->exam_type === 'evaluation_continue');
            $devoir1     = $subjectRawGrades->first(fn($g) => $g->exam?->exam_type === 'devoir_1');
            $devoir2     = $subjectRawGrades->first(fn($g) => $g->exam?->exam_type === 'devoir_2');
            $composition = $subjectRawGrades->first(fn($g) => $g->exam?->exam_type === 'composition');

            $norm = fn($g) => $g ? round(($g->grade / max(1, $g->exam?->max_grade ?? 20)) * 20, 2) : '-';

            return [
                'subject'              => $avg->subject,
                'teacher'             => $subjectRawGrades->first()?->exam?->teacher,
                'evaluation_continue' => $norm($cc),
                'devoir_1'            => $norm($devoir1),
                'devoir_2'            => $norm($devoir2),
                'composition'         => $norm($composition),
                'average'             => $avg->average,
                'coefficient'         => $coefficient,
                'weighted_average'    => round($avg->average * $coefficient, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $student,
                'semester' => $request->semester,
                'academic_year' => $request->academic_year,
                'subjects' => $formattedSubjects,
                'overall_average' => $overallAverageRow ? $overallAverageRow->average : 0,
            ]
        ]);
    }

    /**
     * Get grades for a specific class
     * GET /api/classes/{class_id}/grades
     */
    public function getClassGrades(string $classId, Request $request)
    {
        $query = Grade::with(['student', 'exam.subject', 'exam.teacher', 'exerciseGrades.exercise'])
            ->whereHas('student', fn($q) => $q->where('class_id', $classId));

        if ($request->has('semester')) {
            $query->whereHas('exam', fn($q) => $q->where('semester', $request->semester));
        }
        if ($request->has('academic_year')) {
            $query->whereHas('exam', fn($q) => $q->where('academic_year', $request->academic_year));
        }
        if ($request->has('subject_id')) {
            $query->whereHas('exam', fn($q) => $q->where('subject_id', $request->subject_id));
        }

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = max(1, min(500, $request->integer('per_page', 100)));
            $grades  = $query->orderByDesc('created_at')->paginate($perPage);
        } else {
            $grades = $query->get();
        }

        return response()->json([
            'success' => true,
            'data'    => $grades,
        ]);
    }

    /**
     * Get subject statistics (average, highest, lowest)
     * GET /api/subjects/{subject_id}/statistics?semester={semester}&academic_year={year}&class_id={id}
     */
    public function getSubjectStatistics(string $subjectId, Request $request)
    {
        $subject = Subject::find($subjectId);
        
        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => __('messages.subject_not_found')
            ], 404);
        }

        $query = Grade::whereHas('exam', fn($q) => $q->where('subject_id', $subjectId));

        if ($request->has('semester')) {
            $query->whereHas('exam', fn($q) => $q->where('semester', $request->semester));
        }
        if ($request->has('academic_year')) {
            $query->whereHas('exam', fn($q) => $q->where('academic_year', $request->academic_year));
        }
        if ($request->has('class_id')) {
            $query->whereHas('student', fn($q) => $q->where('class_id', $request->class_id));
        }

        $grades = $query->with('exam')->get();

        if ($grades->isEmpty()) {
            return response()->json(['success' => true, 'data' => ['subject' => $subject, 'total_grades' => 0, 'average' => 0, 'highest_grade' => 0, 'lowest_grade' => 0]]);
        }

        $percentages = $grades->map(fn($g) => ($g->grade / max(1, $g->exam?->max_grade ?? 20)) * 20);

        return response()->json([
            'success' => true,
            'data' => [
                'subject'       => $subject,
                'total_grades'  => $grades->count(),
                'average'       => round($percentages->avg(), 2),
                'highest_grade' => round($percentages->max(), 2),
                'lowest_grade'  => round($percentages->min(), 2),
                'pass_rate'     => round(($percentages->filter(fn($p) => $p >= 10)->count() / $grades->count()) * 100, 2),
            ]
        ]);
    }

    /**
     * Bulk save grades (handles both create and update)
     * POST /api/grades/bulk-save
     */
    public function bulkSave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grades'               => 'required|array|min:1',
            'grades.*.id'         => 'nullable|exists:grades,id',
            'grades.*.student_id' => 'required|exists:students,id',
            'grades.*.exam_id'    => 'required|exists:exams,id',
            'grades.*.grade'      => 'required|numeric|min:0',
            'grades.*.comment'    => 'nullable|string',
            'grades.*.exercise_grades' => 'nullable|array',
            'grades.*.exercise_grades.*.exam_exercise_id' => 'required_with:grades.*.exercise_grades|exists:exam_exercises,id',
            'grades.*.exercise_grades.*.note' => 'required_with:grades.*.exercise_grades|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $examCache     = [];
        $savedGrades   = [];
        $errors        = [];

        DB::beginTransaction();
        try {
            foreach ($request->grades as $index => $gradeData) {
                $examId = $gradeData['exam_id'];
                if (!isset($examCache[$examId])) {
                    $examCache[$examId] = Exam::find($examId);
                }
                $exam = $examCache[$examId];

                if ($gradeData['grade'] > $exam->max_grade) {
                    $errors[] = ['index' => $index, 'message' => "Grade {$gradeData['grade']} exceeds max grade {$exam->max_grade}"];
                    continue;
                }

                if (isset($gradeData['id']) && $gradeData['id']) {
                    // Update existing
                    $grade = Grade::find($gradeData['id']);
                    if ($grade) {
                        $grade->update([
                            'grade'   => $gradeData['grade'],
                            'comment' => $gradeData['comment'] ?? $grade->comment,
                        ]);
                        
                        if (isset($gradeData['exercise_grades'])) {
                            $grade->exerciseGrades()->delete();
                            foreach ($gradeData['exercise_grades'] as $exGrade) {
                                $grade->exerciseGrades()->create([
                                    'exam_exercise_id' => $exGrade['exam_exercise_id'],
                                    'note'             => $exGrade['note'],
                                ]);
                            }
                        }
                    }
                } else {
                    // Create new
                    $grade = Grade::create([
                        'student_id' => $gradeData['student_id'],
                        'exam_id'    => $gradeData['exam_id'],
                        'grade'      => $gradeData['grade'],
                        'comment'    => $gradeData['comment'] ?? null,
                    ]);

                    if (!empty($gradeData['exercise_grades'])) {
                        foreach ($gradeData['exercise_grades'] as $exGrade) {
                            $grade->exerciseGrades()->create([
                                'exam_exercise_id' => $exGrade['exam_exercise_id'],
                                'note'             => $exGrade['note'],
                            ]);
                        }
                    }
                }

                if ($grade) {
                    $savedGrades[] = $grade;
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json(['success' => false, 'errors' => $errors], 422);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'data'    => $savedGrades,
                'count'   => count($savedGrades)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save grades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create grades (Legacy - kept for compatibility)
     * POST /api/grades/bulk
     */
    public function bulkStore(Request $request)
    {
        return $this->bulkSave($request);
    }

    /**
     * GET /api/exams/{exam}/exercise-averages
     * Returns per-exercise average notes for analytics drilldown.
     */
    public function getExamExerciseAverages(string $examId)
    {
        $exam = \App\Models\Exam::with('exercises')->find($examId);
        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Exam not found.'], 404);
        }

        $rows = \App\Models\ExerciseGrade::join('exam_exercises', 'exam_exercises.id', '=', 'exercise_grades.exam_exercise_id')
            ->join('grades', 'grades.id', '=', 'exercise_grades.grade_id')
            ->where('exam_exercises.exam_id', $examId)
            ->selectRaw('exam_exercises.id as exercise_id')
            ->selectRaw('exam_exercises.level_name')
            ->selectRaw('exam_exercises.max_note')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('ROUND(AVG(exercise_grades.note), 2) as avg_note')
            ->selectRaw('ROUND(AVG(CASE WHEN exam_exercises.max_note > 0 THEN (exercise_grades.note / exam_exercises.max_note) * 100 ELSE 0 END), 2) as pass_rate')
            ->groupBy('exam_exercises.id', 'exam_exercises.level_name', 'exam_exercises.max_note')
            ->orderBy('exam_exercises.id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'exam'      => $exam,
                'exercises' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/analytics/subject-exercise-averages
     * Aggregates exercise grades by level_name for a given subject across multiple exams.
     */
    public function getSubjectExerciseAverages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject_id'    => 'nullable|exists:subjects,id',
            'class_id'      => 'nullable|exists:classes,id',
            'academic_year' => 'required|string',
            'semester'      => 'nullable|string',
            'exam_type'     => 'nullable|string',
            'teacher_id'    => 'nullable|exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $query = \App\Models\ExerciseGrade::join('exam_exercises', 'exam_exercises.id', '=', 'exercise_grades.exam_exercise_id')
            ->join('exams', 'exams.id', '=', 'exam_exercises.exam_id')
            ->join('grades', 'grades.id', '=', 'exercise_grades.grade_id')
            ->where('exams.academic_year', $request->input('academic_year'));

        if ($request->filled('subject_id')) {
            $query->where('exams.subject_id', $request->input('subject_id'));
        }

        if ($request->filled('class_id')) {
            $classId = $request->input('class_id');
            $query->whereExists(function ($subQuery) use ($classId) {
                $subQuery->selectRaw('1')
                    ->from('students')
                    ->whereColumn('students.id', 'grades.student_id')
                    ->where('students.class_id', $classId);
            });
        }

        if ($request->filled('semester') && $request->input('semester') !== 'all') {
            $query->where('exams.semester', $request->input('semester'));
        }

        if ($request->filled('exam_type') && $request->input('exam_type') !== 'all') {
            $query->where('exams.exam_type', $request->input('exam_type'));
        }

        if ($request->filled('teacher_id')) {
            $query->where('exams.teacher_id', $request->input('teacher_id'));
        }

        // We only want data belonging to the requested cycle if the user is a director
        $user = auth()->user();
        if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
            $directorCycle = $user->directorCycle();
            $query->join('students', 'students.id', '=', 'grades.student_id')
                  ->join('classes', 'classes.id', '=', 'students.class_id')
                  ->join('levels', 'levels.id', '=', 'classes.level_id')
                  ->where('levels.cycle', $directorCycle);
        }

        $rows = $query->selectRaw('exam_exercises.level_name')
            ->selectRaw('exam_exercises.max_note')
            ->selectRaw('COUNT(*) as records_count')
            ->selectRaw('ROUND(AVG(exercise_grades.note), 2) as avg_note')
            ->groupBy('exam_exercises.level_name', 'exam_exercises.max_note')
            ->orderBy('exam_exercises.level_name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows,
        ]);
    }

    /**
     * Get class ranking based on averages
     * GET /api/classes/{class_id}/ranking?semester={semester}&academic_year={year}
     */
    public function getClassRanking(string $classId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'semester' => 'required|string',
            'academic_year' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rankingsRecords = \App\Models\StudentAverage::with('student')
            ->where('class_id', $classId)
            ->where('record_type', 'overall')
            ->where('trimester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->orderByDesc('average')
            ->get();

        $rankings = $rankingsRecords->map(function ($row, $index) {
            return [
                'student' => [
                    'id' => $row->student->id,
                    'first_name' => $row->student->first_name,
                    'last_name' => $row->student->last_name,
                    'code' => $row->student->code,
                ],
                'average' => $row->average,
                'rank' => $index + 1,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'class_id' => $classId,
                'semester' => $request->semester,
                'academic_year' => $request->academic_year,
                'rankings' => $rankings,
            ]
        ]);
    }

    /**
     * Aggregated analytics endpoint optimized for dashboards.
     * GET /api/grades/analytics/overview
     */
    public function getAnalyticsOverview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'nullable|integer|exists:students,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'teacher_id' => 'nullable|integer|exists:teachers,id',
            'class_id' => 'nullable|integer|exists:classes,id',
            'semester' => 'nullable|string|max:255',
            'academic_year' => 'nullable|string|max:255',
            'exam_type' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $cycle = ($user && method_exists($user, 'isDirector') && $user->isDirector()) ? $user->directorCycle() : 'global';

        $filters = [
            'student_id' => $request->input('student_id', 'all'),
            'subject_id' => $request->input('subject_id', 'all'),
            'teacher_id' => $request->input('teacher_id', 'all'),
            'class_id' => $request->input('class_id', 'all'),
            'semester' => $request->input('semester', 'all'),
            'academic_year' => $request->input('academic_year', 'all'),
            'exam_type' => $request->input('exam_type', 'all'),
            'cycle' => $cycle,
        ];

        $cacheKey = 'grades:analytics:overview:' . md5(json_encode($filters));

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($request) {
            // All analytics queries need the exams join for max_grade / subject / teacher resolution
            $normalizedExpression = 'CASE WHEN exams.max_grade > 0 THEN (grades.grade / exams.max_grade) * 20 ELSE 0 END';

            $statsQuery = Grade::query()->join('exams', 'exams.id', '=', 'grades.exam_id');
            $this->applyGradeFilters($statsQuery, $request);

            $statsRow = $statsQuery
                ->selectRaw('COUNT(*) as records')
                ->selectRaw('COUNT(DISTINCT grades.student_id) as students')
                ->selectRaw('COUNT(DISTINCT exams.teacher_id) as teachers')
                ->selectRaw('COUNT(DISTINCT exams.subject_id) as subjects')
                ->selectRaw('AVG(' . $normalizedExpression . ') as average')
                ->selectRaw('MIN(' . $normalizedExpression . ') as lowest')
                ->selectRaw('MAX(' . $normalizedExpression . ') as highest')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 10 THEN 1 ELSE 0 END) * 100 as pass_rate')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 16 THEN 1 ELSE 0 END) * 100 as excellence_rate')
                ->selectRaw('STDDEV_POP(' . $normalizedExpression . ') as std_dev')
                ->first();

            $classCountQuery = Grade::query()
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->join('students', 'students.id', '=', 'grades.student_id');
            $this->applyGradeFilters($classCountQuery, $request);
            $classesCount = (int) $classCountQuery->distinct('students.class_id')->count('students.class_id');

            $subjectQuery = Grade::query()
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->join('subjects', 'subjects.id', '=', 'exams.subject_id')
                ->selectRaw('exams.subject_id as id, subjects.name as label')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('AVG(' . $normalizedExpression . ') as average')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 10 THEN 1 ELSE 0 END) * 100 as pass_rate')
                ->selectRaw('MIN(' . $normalizedExpression . ') as min')
                ->selectRaw('MAX(' . $normalizedExpression . ') as max')
                ->selectRaw('STDDEV_POP(' . $normalizedExpression . ') as std_dev')
                ->groupBy('exams.subject_id', 'subjects.name')
                ->orderByDesc('average');
            $this->applyGradeFilters($subjectQuery, $request);
            $subjectAggregates = $subjectQuery->get();

            $classQuery = Grade::query()
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->join('students', 'students.id', '=', 'grades.student_id')
                ->join('classes', 'classes.id', '=', 'students.class_id')
                ->selectRaw('students.class_id as id, classes.name as label')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('AVG(' . $normalizedExpression . ') as average')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 10 THEN 1 ELSE 0 END) * 100 as pass_rate')
                ->selectRaw('MIN(' . $normalizedExpression . ') as min')
                ->selectRaw('MAX(' . $normalizedExpression . ') as max')
                ->selectRaw('STDDEV_POP(' . $normalizedExpression . ') as std_dev')
                ->groupBy('students.class_id', 'classes.name')
                ->orderByDesc('average');
            $this->applyGradeFilters($classQuery, $request);
            $classAggregates = $classQuery->get();

            $teacherQuery = Grade::query()
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->join('teachers', 'teachers.id', '=', 'exams.teacher_id')
                ->selectRaw('exams.teacher_id as id, CONCAT(teachers.first_name, " ", teachers.last_name) as label')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('AVG(' . $normalizedExpression . ') as average')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 10 THEN 1 ELSE 0 END) * 100 as pass_rate')
                ->selectRaw('MIN(' . $normalizedExpression . ') as min')
                ->selectRaw('MAX(' . $normalizedExpression . ') as max')
                ->selectRaw('STDDEV_POP(' . $normalizedExpression . ') as std_dev')
                ->groupBy('exams.teacher_id', 'teachers.first_name', 'teachers.last_name')
                ->orderByDesc('average');
            $this->applyGradeFilters($teacherQuery, $request);
            $teacherAggregates = $teacherQuery->get();

            $distributionQuery = Grade::query()->join('exams', 'exams.id', '=', 'grades.exam_id');
            $this->applyGradeFilters($distributionQuery, $request);
            $distribution = $distributionQuery
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 0 AND ' . $normalizedExpression . ' < 5 THEN 1 ELSE 0 END) as band_0_5')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 5 AND ' . $normalizedExpression . ' < 10 THEN 1 ELSE 0 END) as band_5_10')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 10 AND ' . $normalizedExpression . ' < 12 THEN 1 ELSE 0 END) as band_10_12')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 12 AND ' . $normalizedExpression . ' < 14 THEN 1 ELSE 0 END) as band_12_14')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 14 AND ' . $normalizedExpression . ' < 16 THEN 1 ELSE 0 END) as band_14_16')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 16 THEN 1 ELSE 0 END) as band_16_20')
                ->first();

            $studentQuery = Grade::query()
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->join('students', 'students.id', '=', 'grades.student_id')
                ->join('classes', 'classes.id', '=', 'students.class_id')
                ->selectRaw('grades.student_id as id')
                ->selectRaw('CONCAT(students.first_name, " ", students.last_name) as label')
                ->selectRaw('students.class_id as class_id')
                ->selectRaw('classes.name as class_name')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('AVG(' . $normalizedExpression . ') as average')
                ->selectRaw('MIN(' . $normalizedExpression . ') as min')
                ->selectRaw('MAX(' . $normalizedExpression . ') as max')
                ->selectRaw('STDDEV_POP(' . $normalizedExpression . ') as std_dev')
                ->groupBy('grades.student_id', 'students.first_name', 'students.last_name', 'students.class_id', 'classes.name')
                ->orderByDesc('average');
            $this->applyGradeFilters($studentQuery, $request);
            $studentAggregates = $studentQuery->get();

            // Pass rate based on subject averages:
            // For each (student, subject) compute the average grade, then count how many subjects >= 10
            $subjAvgQuery = Grade::query()
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->selectRaw('grades.student_id as student_id, exams.subject_id as subject_id, AVG(' . $normalizedExpression . ') as subj_avg')
                ->groupBy('grades.student_id', 'exams.subject_id');
            $this->applyGradeFilters($subjAvgQuery, $request);

            $passRateMap = DB::table(DB::raw('(' . $subjAvgQuery->toSql() . ') as sub'))
                ->mergeBindings($subjAvgQuery->getQuery())
                ->selectRaw('student_id')
                ->selectRaw('ROUND(AVG(CASE WHEN subj_avg >= 10 THEN 100 ELSE 0 END), 2) as pass_rate')
                ->groupBy('student_id')
                ->get()
                ->keyBy('student_id');

            // Best subject per student: find the subject with the highest avg
            $bestSubjectQuery = Grade::query()
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->join('subjects', 'subjects.id', '=', 'exams.subject_id')
                ->selectRaw('grades.student_id')
                ->selectRaw('subjects.name as best_subject')
                ->selectRaw('AVG(' . $normalizedExpression . ') as best_avg')
                ->groupBy('grades.student_id', 'exams.subject_id', 'subjects.name');
            $this->applyGradeFilters($bestSubjectQuery, $request);

            // Pick the top subject per student
            $bestSubjectMap = [];
            foreach ($bestSubjectQuery->get() as $row) {
                $sid = $row->student_id;
                if (!isset($bestSubjectMap[$sid]) || $row->best_avg > $bestSubjectMap[$sid]['avg']) {
                    $bestSubjectMap[$sid] = ['subject' => $row->best_subject, 'avg' => round((float)$row->best_avg, 2)];
                }
            }

            // Merge pass_rate + best_subject into student aggregates
            $studentAggregates = $studentAggregates->map(function ($row) use ($bestSubjectMap, $passRateMap) {
                $sid = $row->id;
                $row->pass_rate       = (float) ($passRateMap[$sid]->pass_rate ?? 0);
                $row->best_subject    = $bestSubjectMap[$sid]['subject'] ?? null;
                $row->best_subject_avg = $bestSubjectMap[$sid]['avg'] ?? null;
                return $row;
            });

            return [
                'stats' => [
                    'records' => (int) ($statsRow->records ?? 0),
                    'students' => (int) ($statsRow->students ?? 0),
                    'teachers' => (int) ($statsRow->teachers ?? 0),
                    'subjects' => (int) ($statsRow->subjects ?? 0),
                    'classes' => $classesCount,
                    'average' => round((float) ($statsRow->average ?? 0), 2),
                    // Median is omitted to avoid expensive percentile scans for dashboard traffic.
                    'median' => round((float) ($statsRow->average ?? 0), 2),
                    'highest' => round((float) ($statsRow->highest ?? 0), 2),
                    'lowest' => round((float) ($statsRow->lowest ?? 0), 2),
                    'passRate' => round((float) ($statsRow->pass_rate ?? 0), 2),
                    'excellenceRate' => round((float) ($statsRow->excellence_rate ?? 0), 2),
                    'stdDev' => round((float) ($statsRow->std_dev ?? 0), 2),
                ],
                'subject_aggregates' => $subjectAggregates,
                'class_aggregates' => $classAggregates,
                'teacher_aggregates' => $teacherAggregates,
                'student_aggregates' => $studentAggregates,
                'distribution' => [
                    ['label' => '0-5', 'count' => (int) ($distribution->band_0_5 ?? 0)],
                    ['label' => '5-10', 'count' => (int) ($distribution->band_5_10 ?? 0)],
                    ['label' => '10-12', 'count' => (int) ($distribution->band_10_12 ?? 0)],
                    ['label' => '12-14', 'count' => (int) ($distribution->band_12_14 ?? 0)],
                    ['label' => '14-16', 'count' => (int) ($distribution->band_14_16 ?? 0)],
                    ['label' => '16-20', 'count' => (int) ($distribution->band_16_20 ?? 0)],
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
