<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\Validator;


class GradeController extends Controller
{
    /**
     * Display a listing of the resource.
     * Can be filtered by student_id, subject_id, semester, academic_year
     * GET /api/grades?student_id={id}&subject_id={id}&semester={semester}
     */
    public function index(Request $request)
    {
        $query = Grade::with(['student', 'subject', 'teacher']);
        
        // Filter by student_id if provided
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        
        // Filter by subject_id if provided
        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        
        // Filter by semester if provided
        if ($request->has('semester')) {
            $query->where('semester', $request->semester);
        }
        
        // Filter by academic_year if provided
        if ($request->has('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }
        
        $grades = $query->get();
        
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
                'message' => 'Student not found.'
            ], 404);
        }

        // Validate that the subject exists
        $subject = Subject::find($request->subject_id);
        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found.'
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
                'message' => 'Grade not found.'
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
                'message' => 'Grade not found.'
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
                    'message' => 'Grade cannot be greater than max grade.'
                ], 422);
            }
        }

        $grade->update($request->all());
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
                'message' => 'Grade not found.'
            ], 404);
        }

        $grade->delete();

        return response()->json([
            'success' => true,
            'message' => 'Grade deleted successfully.'
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
                'message' => 'Student not found.'
            ], 404);
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
                'message' => 'Student not found.'
            ], 404);
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

        $grades = $query->get();

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
                'message' => 'Subject not found.'
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
                    'message' => 'Grade cannot be greater than max grade.'
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
                    'message' => 'Failed to create grade: ' . $e->getMessage()
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

        $students = Student::where('class_id', $classId)->get();
        $rankings = [];

        foreach ($students as $student) {
            $grades = Grade::where('student_id', $student->id)
                ->where('semester', $request->semester)
                ->where('academic_year', $request->academic_year)
                ->get();

            if ($grades->isEmpty()) {
                continue;
            }

            // Group by subject and calculate averages
            $gradesBySubject = $grades->groupBy('subject_id');
            $subjectAverages = [];

            foreach ($gradesBySubject as $subjectGrades) {
                $totalGrade = 0;
                $totalMaxGrade = 0;

                foreach ($subjectGrades as $grade) {
                    $totalGrade += $grade->grade;
                    $totalMaxGrade += $grade->max_grade;
                }

                if ($totalMaxGrade > 0) {
                    $subjectAverages[] = ($totalGrade / $totalMaxGrade) * 20;
                }
            }

            if (!empty($subjectAverages)) {
                $overallAverage = array_sum($subjectAverages) / count($subjectAverages);
                $rankings[] = [
                    'student' => $student,
                    'average' => round($overallAverage, 2),
                ];
            }
        }

        // Sort by average descending
        usort($rankings, function ($a, $b) {
            return $b['average'] <=> $a['average'];
        });

        // Add rank
        foreach ($rankings as $index => &$ranking) {
            $ranking['rank'] = $index + 1;
        }

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
}
