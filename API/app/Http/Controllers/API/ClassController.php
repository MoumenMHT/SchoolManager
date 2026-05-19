<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentHistory;
use App\Models\Schedule;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $query = SchoolClass::with(['mainTeacher', 'students', 'teachers', 'subjects', 'levelProfile']);
            
            $user = auth()->user();
            if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
                $directorCycle = $user->directorCycle();
                $query->whereHas('levelProfile', function($q) use ($directorCycle) {
                    $q->where('cycle', $directorCycle);
                });
            }

            $classes = $query->get()
                ->map(function ($class) {
                    $studentsData = [];
                    if ($class->students) {
                        foreach ($class->students as $student) {
                            $studentsData[] = [
                                'id' => $student->id,
                                'first_name' => $student->first_name ?? '',
                                'last_name' => $student->last_name ?? '',
                                'code' => $student->code ?? '',
                                'birth_date' => $student->birth_date ? $student->birth_date->format('Y-m-d') : null,
                                'mdical_info' => $student->medical_info ?? null,
                            ];
                        }
                    }

                    $teachersData = [];
                    if ($class->teachers) {
                        foreach ($class->teachers as $teacher) {
                            $teachersData[] = [
                                'name' => ($teacher->first_name ?? '') . ' ' . ($teacher->last_name ?? ''),
                            ];
                        }
                    }

                    $subjectsData = [];
                    if ($class->subjects) {
                        foreach ($class->subjects as $subject) {
                            $subjectsData[] = [
                                'name' => $subject->name ?? '',
                                'discription' => $subject->description ?? '',
                            ];
                        }
                    }

                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'level' => $class->levelProfile->name ?? $class->level,
                        'level_id' => $class->level_id,
                        'academic_year' => $class->academic_year,
                        'capacity' => $class->capacity,
                        'main_teacher_id' => $class->main_teacher_id,
                        'is_active' => $class->is_active,
                        'created_at' => $class->created_at,
                        'updated_at' => $class->updated_at,
                        'students_count' => $class->students ? $class->students->count() : 0,
                        'teachers_count' => $class->teachers ? $class->teachers->count() : 0,
                        'students' => $studentsData,
                        'teachers' => $teachersData,
                        'subjects' => $subjectsData,
                    ];
                });
                
            return response()->json([
                'success' => true,
                'data' => $classes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_classes'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:classes,name',
                'level' => 'nullable|string|max:255',
                'level_id' => 'nullable|exists:levels,id',
                'academic_year' => 'nullable|string|max:255',
                'capacity' => 'nullable|integer|min:1',
                'main_teacher_id' => 'nullable|exists:teachers,id',
            ]);

            if (!$request->filled('level') && !$request->filled('level_id')) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => [
                        'level' => ['The level or level_id field is required.']
                    ]
                ], 422);
            }

            $resolvedLevelId = $this->resolveLevelId($request);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $class = SchoolClass::create([
                'name' => $request->name,
                'level' => $request->level,
                'level_id' => $resolvedLevelId,
                'academic_year' => $request->academic_year,
                'capacity' => $request->capacity,
                'is_active' => true,
                'main_teacher_id' => $request->main_teacher_id,
            ]);

            $class->load(['mainTeacher', 'levelProfile']);

            return response()->json([
                'success' => true,
                'message' => __('messages.class_created'),
                'data' => $class
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_create_class'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $class = SchoolClass::with(['mainTeacher', 'students', 'teachers', 'subjects', 'levelProfile'])->find($id);
            
            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.class_not_found')
                ], 404);
            }
            
            $studentsData = [];
            if ($class->students) {
                foreach ($class->students as $student) {
                    $studentsData[] = [
                        'id' => $student->id,
                        'first_name' => $student->first_name ?? '',
                        'last_name' => $student->last_name ?? '',
                        'code' => $student->code ?? '',
                        'birth_date' => $student->birth_date ? $student->birth_date->format('Y-m-d') : null,
                        'mdical_info' => $student->medical_info ?? null,
                    ];
                }
            }

            $teachersData = [];
            if ($class->teachers) {
                foreach ($class->teachers as $teacher) {
                    $teachersData[] = [
                        'name' => ($teacher->first_name ?? '') . ' ' . ($teacher->last_name ?? ''),
                    ];
                }
            }

            $subjectsData = [];
            if ($class->subjects) {
                foreach ($class->subjects as $subject) {
                    $subjectsData[] = [
                        'name' => $subject->name ?? '',
                        'discription' => $subject->description ?? '',
                    ];
                }
            }
            
            $data = [
                'id' => $class->id,
                'name' => $class->name,
                'level' => $class->levelProfile->name ?? $class->level,
                'level_id' => $class->level_id,
                'academic_year' => $class->academic_year,
                'capacity' => $class->capacity,
                'main_teacher_id' => $class->main_teacher_id,
                'is_active' => $class->is_active,
                'created_at' => $class->created_at,
                'updated_at' => $class->updated_at,
                'students_count' => $class->students ? $class->students->count() : 0,
                'teachers_count' => $class->teachers ? $class->teachers->count() : 0,
                'sudents' => $studentsData,
                'teachers' => $teachersData,
                'subjects' => $subjectsData,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_class'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
        try {
            $class = SchoolClass::find($id);
            
            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.class_not_found')
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:classes,name,' . $id,
                'level' => 'sometimes|nullable|string|max:255',
                'level_id' => 'sometimes|nullable|exists:levels,id',
                'academic_year' => 'sometimes|nullable|string|max:255',
                'capacity' => 'sometimes|nullable|integer|min:1',
                'main_teacher_id' => 'nullable|exists:teachers,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $resolvedLevelId = $class->level_id;
            if ($request->has('level') || $request->has('level_id')) {
                $resolvedLevelId = $this->resolveLevelId($request);
            }

            $class->update($request->only([
                'name',
                'level',
                'academic_year',
                'capacity',
                'main_teacher_id'
            ]));

            // Update assignments academic year if updated
            if ($request->has('academic_year')) {
                \App\Models\ClassSubjectTeacher::where('class_id', $class->id)->update([
                    'academic_year' => $request->academic_year
                ]);
            }

            $class->level_id = $resolvedLevelId;
            $class->save();

            $class->load(['mainTeacher', 'levelProfile']);
            
            return response()->json([
                'success' => true,
                'message' => __('messages.class_updated'),
                'data' => $class
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_update_class'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $class = SchoolClass::find($id);
            
            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.class_not_found')
                ], 404);
            }

            // Check if class has students
            if ($class->students()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.cannot_delete_with_students')
                ], 409);
            }

            $class->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.class_deleted')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_delete_class'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * remove student from a class
     */

    public function removeStudentFromClass($studentId){

        $student = Student::findOrFail($studentId);

        StudentHistory::where('student_id', $studentId)
            ->whereNull('left_at')
            ->update(['left_at' => now()->toDateString()]);

        $student->class_id = null;
        $student->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.student_removed_from_class'),
            'data' => $student
        ]);

    }

    /**
     * Get the schedule for a student (used by the parent portal).
     * The route is: GET /parent/students/{student}/schedule
     */
    public function studentSchedule(Request $request, $studentId)
    {
        try {
            $parent = auth()->user()->parent;

            // Find the student and verify the authenticated parent owns them
            $student = Student::with('class')->where('id', $studentId)
                ->where('parent_id', $parent?->id)
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.student_not_found')
                ], 404);
            }

            if (!$student->class_id) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'total'   => 0,
                    'message' => 'Student is not assigned to any class.'
                ]);
            }

            $academicYear = $request->get('academic_year', $student->class->academic_year ?? (date('Y') . '-' . (date('Y') + 1)));

            $schedules = Schedule::with(['assignment.subject', 'assignment.teacher'])
                ->whereHas('assignment', function ($q) use ($student, $academicYear) {
                    $q->where('class_id', $student->class_id)
                      ->where('academic_year', $academicYear);
                })
                ->orderByRaw("FIELD(day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')")
                ->orderBy('start_time')
                ->get();

            // Normalize day to lowercase for frontend compatibility
            $schedules->transform(function ($schedule) {
                $schedule->day = strtolower($schedule->day);
                return $schedule;
            });

            $groupedSchedules = $schedules->groupBy('day');

            return response()->json([
                'success' => true,
                'data'    => $groupedSchedules,
                'total'   => $schedules->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student schedule.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    private function resolveLevelId(Request $request): ?int
    {
        if ($request->filled('level_id')) {
            return (int) $request->level_id;
        }

        if (!$request->filled('level')) {
            return null;
        }

        $levelName = trim((string) $request->level);
        $existing = Level::where('name', $levelName)->first();

        if ($existing) {
            return $existing->id;
        }

        $nextSortOrder = (int) Level::max('sort_order') + 1;

        $level = Level::create([
            'cycle' => 'primary',
            'year_number' => $nextSortOrder,
            'track' => null,
            'name' => $levelName,
            'sort_order' => $nextSortOrder,
            'is_active' => true,
        ]);

        return $level->id;
    }
}
