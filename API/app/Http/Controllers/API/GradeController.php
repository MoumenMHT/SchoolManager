<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class GradeController extends Controller
{
    private function applyGradeFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('student_id')) {
            $query->where('grades.student_id', $request->integer('student_id'));
        }

        if ($request->filled('subject_id')) {
            $query->where('grades.subject_id', $request->integer('subject_id'));
        }

        if ($request->filled('teacher_id')) {
            $query->where('grades.teacher_id', $request->integer('teacher_id'));
        }

        if ($request->filled('semester')) {
            $query->where('grades.semester', $request->input('semester'));
        }

        if ($request->filled('academic_year')) {
            $query->where('grades.academic_year', $request->input('academic_year'));
        }

        if ($request->filled('exam_type')) {
            $query->where('grades.exam_type', $request->input('exam_type'));
        }

        if ($request->filled('class_id')) {
            $classId = $request->integer('class_id');
            $query->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
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
        $query = Grade::with(['student', 'subject', 'teacher']);
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
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'exam_type' => 'required|string|max:255',
            'grade' => 'required|numeric|min:0',
            'max_grade' => 'required|numeric|min:0|gte:grade',
            'semester' => 'required|string|max:255',
            'academic_year' => 'required|string|max:255',
            'comment' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate that the student exists
        $student = Student::find($request->student_id);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_not_found')
            ], 404);
        }

        // Validate that the subject exists
        $subject = Subject::find($request->subject_id);
        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => __('messages.subject_not_found')
            ], 404);
        }

        $grade = Grade::create([
            'student_id' => $request->student_id,
            'subject_id' => $request->subject_id,
            'teacher_id' => $request->teacher_id,
            'exam_type' => $request->exam_type,
            'grade' => $request->grade,
            'max_grade' => $request->max_grade,
            'semester' => $request->semester,
            'academic_year' => $request->academic_year,
            'comment' => $request->comment,
        ]);

        $grade->load(['student', 'subject', 'teacher']);

        return response()->json([
            'success' => true,
            'data' => $grade,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $grade = Grade::with(['student', 'subject', 'teacher'])->find($id);
        
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
            'student_id' => 'sometimes|required|exists:students,id',
            'subject_id' => 'sometimes|required|exists:subjects,id',
            'teacher_id' => 'sometimes|required|exists:teachers,id',
            'exam_type' => 'sometimes|required|string|max:255',
            'grade' => 'sometimes|required|numeric|min:0',
            'max_grade' => 'sometimes|required|numeric|min:0',
            'semester' => 'sometimes|required|string|max:255',
            'academic_year' => 'sometimes|required|string|max:255',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate grade and max_grade relationship if both are provided
        if ($request->has('grade') && $request->has('max_grade')) {
            if ($request->grade > $request->max_grade) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.grade_exceeds_max')
                ], 422);
            }
        }

        $grade->update($request->only([
            'student_id', 'subject_id', 'teacher_id', 'exam_type',
            'grade', 'max_grade', 'semester', 'academic_year', 'comment'
        ]));
        $grade->load(['student', 'subject', 'teacher']);

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

        $query = Grade::with(['subject', 'teacher'])
            ->where('student_id', $studentId);

        // Filter by semester if provided
        if ($request->has('semester')) {
            $query->where('semester', $request->semester);
        }

        // Filter by academic_year if provided
        if ($request->has('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }

        // Filter by subject_id if provided
        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        $grades = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $student,
                'grades' => $grades,
            ]
        ]);
    }

    /**
     * Get report card for a student with calculated averages
     * GET /api/students/{student_id}/report-card?semester={semester}&academic_year={year}
     */
    public function getStudentReportCard(string $studentId, Request $request)
    {
        $student = Student::with('class')->find($studentId);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_not_found')
            ], 404);
        }

        // Parents can only access report cards for their own children
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

        $grades = Grade::with(['subject', 'teacher'])
            ->where('student_id', $studentId)
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->get();

        // Group grades by subject
        $gradesBySubject = $grades->groupBy('subject_id')->map(function ($subjectGrades) {
            $subject = $subjectGrades->first()->subject;
            $totalGrade = 0;
            $totalMaxGrade = 0;
    
            foreach ($subjectGrades as $grade) {
                $totalGrade += $grade->grade;
                $totalMaxGrade += $grade->max_grade;
            }

            $average = $totalMaxGrade > 0 ? ($totalGrade / $totalMaxGrade) * 20 : 0;

            return [
                'subject' => $subject,
                'grades' => $subjectGrades,
                'average' => round($average, 2),
            ];
        });

        // Calculate overall average
        $totalAverage = 0;
        $subjectCount = $gradesBySubject->count();
        
        if ($subjectCount > 0) {
            foreach ($gradesBySubject as $subjectData) {
                $totalAverage += $subjectData['average'];
            }
            $totalAverage = round($totalAverage / $subjectCount, 2);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $student,
                'semester' => $request->semester,
                'academic_year' => $request->academic_year,
                'subjects' => $gradesBySubject->values(),
                'overall_average' => $totalAverage,
            ]
        ]);
    }

    /**
     * Get grades for a specific class
     * GET /api/classes/{class_id}/grades
     */
    public function getClassGrades(string $classId, Request $request)
    {
        $query = Grade::with(['student', 'subject', 'teacher'])
            ->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });

        // Filter by semester if provided
        if ($request->has('semester')) {
            $query->where('semester', $request->semester);
        }

        // Filter by academic_year if provided
        if ($request->has('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }

        // Filter by subject_id if provided
        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

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

        $query = Grade::where('subject_id', $subjectId);

        // Filter by semester if provided
        if ($request->has('semester')) {
            $query->where('semester', $request->semester);
        }

        // Filter by academic_year if provided
        if ($request->has('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }

        // Filter by class if provided
        if ($request->has('class_id')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        $grades = $query->get();

        if ($grades->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'subject' => $subject,
                    'total_grades' => 0,
                    'average' => 0,
                    'highest_grade' => 0,
                    'lowest_grade' => 0,
                ]
            ]);
        }

        // Calculate percentages for each grade
        $percentages = $grades->map(function ($grade) {
            return ($grade->grade / $grade->max_grade) * 20;
        });

        $average = $percentages->avg();
        $highest = $percentages->max();
        $lowest = $percentages->min();

        return response()->json([
            'success' => true,
            'data' => [
                'subject' => $subject,
                'total_grades' => $grades->count(),
                'average' => round($average, 2),
                'highest_grade' => round($highest, 2),
                'lowest_grade' => round($lowest, 2),
                'pass_rate' => round(($percentages->filter(fn($p) => $p >= 10)->count() / $grades->count()) * 100, 2),
            ]
        ]);
    }

    /**
     * Bulk create grades (useful for entering grades for multiple students)
     * POST /api/grades/bulk
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grades' => 'required|array|min:1',
            'grades.*.student_id' => 'required|exists:students,id',
            'grades.*.subject_id' => 'required|exists:subjects,id',
            'grades.*.teacher_id' => 'required|exists:teachers,id',
            'grades.*.exam_type' => 'required|string|max:255',
            'grades.*.grade' => 'required|numeric|min:0',
            'grades.*.max_grade' => 'required|numeric|min:0',
            'grades.*.semester' => 'required|string|max:255',
            'grades.*.academic_year' => 'required|string|max:255',
            'grades.*.comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $createdGrades = [];
        $errors = [];

        foreach ($request->grades as $index => $gradeData) {
            // Validate grade vs max_grade
            if ($gradeData['grade'] > $gradeData['max_grade']) {
                $errors[] = [
                    'index' => $index,
                    'message' => __('messages.grade_exceeds_max')
                ];
                continue;
            }

            try {
                $grade = Grade::create($gradeData);
                $grade->load(['student', 'subject', 'teacher']);
                $createdGrades[] = $grade;
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'message' => config('app.debug') ? __('messages.failed_create_grade') . ': ' . $e->getMessage() : __('messages.failed_create_grade')
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $createdGrades,
            'errors' => $errors,
            'created_count' => count($createdGrades),
            'failed_count' => count($errors),
        ], 201);
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

        $studentIds = Student::where('class_id', $classId)->pluck('id');

        if ($studentIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'class_id' => $classId,
                    'semester' => $request->semester,
                    'academic_year' => $request->academic_year,
                    'rankings' => [],
                ]
            ]);
        }

        $subjectAveragesSubquery = Grade::query()
            ->selectRaw('student_id, subject_id, (SUM(grade) / NULLIF(SUM(max_grade), 0)) * 20 as subject_average')
            ->whereIn('student_id', $studentIds)
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->groupBy('student_id', 'subject_id');

        $rankings = DB::query()
            ->fromSub($subjectAveragesSubquery, 'subject_averages')
            ->join('students', 'students.id', '=', 'subject_averages.student_id')
            ->selectRaw('students.id as student_id, students.first_name, students.last_name, students.code, AVG(subject_averages.subject_average) as average')
            ->groupBy('students.id', 'students.first_name', 'students.last_name', 'students.code')
            ->orderByDesc('average')
            ->get()
            ->values()
            ->map(function ($row, $index) {
                return [
                    'student' => [
                        'id' => $row->student_id,
                        'first_name' => $row->first_name,
                        'last_name' => $row->last_name,
                        'code' => $row->code,
                    ],
                    'average' => round((float) $row->average, 2),
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

        $filters = [
            'student_id' => $request->input('student_id', 'all'),
            'subject_id' => $request->input('subject_id', 'all'),
            'teacher_id' => $request->input('teacher_id', 'all'),
            'class_id' => $request->input('class_id', 'all'),
            'semester' => $request->input('semester', 'all'),
            'academic_year' => $request->input('academic_year', 'all'),
            'exam_type' => $request->input('exam_type', 'all'),
        ];

        $cacheKey = 'grades:analytics:overview:' . md5(json_encode($filters));

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($request) {
            $normalizedExpression = 'CASE WHEN grades.max_grade > 0 THEN (grades.grade / grades.max_grade) * 20 ELSE 0 END';

            $statsQuery = Grade::query();
            $this->applyGradeFilters($statsQuery, $request);

            $statsRow = $statsQuery
                ->selectRaw('COUNT(*) as records')
                ->selectRaw('COUNT(DISTINCT student_id) as students')
                ->selectRaw('COUNT(DISTINCT teacher_id) as teachers')
                ->selectRaw('COUNT(DISTINCT subject_id) as subjects')
                ->selectRaw('AVG(' . $normalizedExpression . ') as average')
                ->selectRaw('MIN(' . $normalizedExpression . ') as lowest')
                ->selectRaw('MAX(' . $normalizedExpression . ') as highest')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 10 THEN 1 ELSE 0 END) * 100 as pass_rate')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 16 THEN 1 ELSE 0 END) * 100 as excellence_rate')
                ->selectRaw('STDDEV_POP(' . $normalizedExpression . ') as std_dev')
                ->first();

            $classCountQuery = Grade::query()->join('students', 'students.id', '=', 'grades.student_id');
            $this->applyGradeFilters($classCountQuery, $request);
            $classesCount = (int) $classCountQuery->distinct('students.class_id')->count('students.class_id');

            $subjectQuery = Grade::query()
                ->join('subjects', 'subjects.id', '=', 'grades.subject_id')
                ->selectRaw('grades.subject_id as id, subjects.name as label')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('AVG(' . $normalizedExpression . ') as average')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 10 THEN 1 ELSE 0 END) * 100 as pass_rate')
                ->selectRaw('MIN(' . $normalizedExpression . ') as min')
                ->selectRaw('MAX(' . $normalizedExpression . ') as max')
                ->selectRaw('STDDEV_POP(' . $normalizedExpression . ') as std_dev')
                ->groupBy('grades.subject_id', 'subjects.name')
                ->orderByDesc('average');
            $this->applyGradeFilters($subjectQuery, $request);
            $subjectAggregates = $subjectQuery->get();

            $classQuery = Grade::query()
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
                ->join('teachers', 'teachers.id', '=', 'grades.teacher_id')
                ->selectRaw('grades.teacher_id as id, CONCAT(teachers.first_name, " ", teachers.last_name) as label')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('AVG(' . $normalizedExpression . ') as average')
                ->selectRaw('AVG(CASE WHEN ' . $normalizedExpression . ' >= 10 THEN 1 ELSE 0 END) * 100 as pass_rate')
                ->selectRaw('MIN(' . $normalizedExpression . ') as min')
                ->selectRaw('MAX(' . $normalizedExpression . ') as max')
                ->selectRaw('STDDEV_POP(' . $normalizedExpression . ') as std_dev')
                ->groupBy('grades.teacher_id', 'teachers.first_name', 'teachers.last_name')
                ->orderByDesc('average');
            $this->applyGradeFilters($teacherQuery, $request);
            $teacherAggregates = $teacherQuery->get();

            $distributionQuery = Grade::query();
            $this->applyGradeFilters($distributionQuery, $request);
            $distribution = $distributionQuery
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 0 AND ' . $normalizedExpression . ' < 5 THEN 1 ELSE 0 END) as band_0_5')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 5 AND ' . $normalizedExpression . ' < 10 THEN 1 ELSE 0 END) as band_5_10')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 10 AND ' . $normalizedExpression . ' < 12 THEN 1 ELSE 0 END) as band_10_12')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 12 AND ' . $normalizedExpression . ' < 14 THEN 1 ELSE 0 END) as band_12_14')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 14 AND ' . $normalizedExpression . ' < 16 THEN 1 ELSE 0 END) as band_14_16')
                ->selectRaw('SUM(CASE WHEN ' . $normalizedExpression . ' >= 16 THEN 1 ELSE 0 END) as band_16_20')
                ->first();

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
