<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\ClassSubjectTeacher;
use App\Models\LevelSubject;
use App\Models\Teacher;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    private array $generationDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
    private int $teacherWeeklyTargetSessions = 20;
    private array $sessionSlots = [
        ['start' => '08:00', 'end' => '09:00', 'index' => 1],
        ['start' => '09:00', 'end' => '10:00', 'index' => 2],
        ['start' => '10:00', 'end' => '11:00', 'index' => 3],
        ['start' => '11:00', 'end' => '12:00', 'index' => 4],
        ['start' => '13:00', 'end' => '14:00', 'index' => 5],
        ['start' => '14:00', 'end' => '15:00', 'index' => 6],
        ['start' => '15:00', 'end' => '16:00', 'index' => 7],
        ['start' => '16:00', 'end' => '17:00', 'index' => 8],
    ];
    /**
     * Display a listing of schedules with advanced filtering and pagination.
     * 
     * Query Parameters:
     * - class_id: Filter by class
     * - teacher_id: Filter by teacher
     * - subject_id: Filter by subject
     * - day: Filter by day (monday, tuesday, etc.)
     * - room: Filter by room
     * - academic_year: Filter by academic year
     * - start_time: Filter schedules starting after this time (H:i format)
     * - end_time: Filter schedules ending before this time (H:i format)
     * - date: Get schedule for a specific date (will determine the day)
     * - sort_by: Sort field (default: day, start_time)
     * - sort_order: asc or desc (default: asc)
     * - per_page: Items per page (default: 15)
     * - with_relations: Include relationships (class,subject,teacher)
     */
    public function index(Request $request)
    {
        try {
            $query = Schedule::query();

            // Include relationships by default
            $withRelations = $request->get('with_relations', 'class,subject,teacher');
            if ($withRelations) {
                $relations = array_map('trim', explode(',', $withRelations));
                $query->with(['assignment' => function ($q) use ($relations) {
                    if (in_array('class', $relations)) {
                        $q->with('class');
                    }
                    if (in_array('subject', $relations)) {
                        $q->with('subject');
                    }
                    if (in_array('teacher', $relations)) {
                        $q->with('teacher');
                    }
                }]);
            }

            // Filter by class_id
            if ($request->has('class_id')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('class_id', $request->class_id);
                });
            }

            // Filter by teacher_id
            if ($request->has('teacher_id')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('teacher_id', $request->teacher_id);
                });
            }

            // Filter by subject_id
            if ($request->has('subject_id')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('subject_id', $request->subject_id);
                });
            }

            // Filter by academic year
            if ($request->has('academic_year')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('academic_year', $request->academic_year);
                });
            }

            // Filter by day
            if ($request->has('day')) {
                $query->where('day', strtolower($request->day));
            }

            // Filter by room
            if ($request->has('room')) {
                $query->where('room', 'like', '%' . $request->room . '%');
            }

            // Filter by date (converts date to day of week)
            if ($request->has('date')) {
                try {
                    $date = Carbon::parse($request->date);
                    $dayName = strtolower($date->format('l')); // monday, tuesday, etc.
                    $query->where('day', $dayName);
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.invalid_date_format')
                    ], 400);
                }
            }

            // Filter by start_time (after)
            if ($request->has('start_time_after')) {
                $query->where('start_time', '>=', $request->start_time_after);
            }

            // Filter by end_time (before)
            if ($request->has('end_time_before')) {
                $query->where('end_time', '<=', $request->end_time_before);
            }

            // Time range filter (schedules within a time range)
            if ($request->has('time_from') && $request->has('time_to')) {
                $query->where(function ($q) use ($request) {
                    $q->whereBetween('start_time', [$request->time_from, $request->time_to])
                      ->orWhereBetween('end_time', [$request->time_from, $request->time_to])
                      ->orWhere(function ($q2) use ($request) {
                          $q2->where('start_time', '<=', $request->time_from)
                             ->where('end_time', '>=', $request->time_to);
                      });
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'day');
            $sortOrder = $request->get('sort_order', 'asc');
            
            // Custom sorting for day (to ensure proper week order)
            if ($sortBy === 'day') {
                $this->orderByDayOfWeek($query, $sortOrder);
                $query->orderBy('start_time', 'asc'); // Secondary sort by start time
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            
            if ($perPage === 'all') {
                $schedules = $query->get();
                return response()->json([
                    'success' => true,
                    'data' => $schedules,
                    'total' => $schedules->count()
                ]);
            }

            $schedules = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $schedules->items(),
                'pagination' => [
                    'total' => $schedules->total(),
                    'per_page' => $schedules->perPage(),
                    'current_page' => $schedules->currentPage(),
                    'last_page' => $schedules->lastPage(),
                    'from' => $schedules->firstItem(),
                    'to' => $schedules->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_schedules'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created schedule.
     */
    public function store(Request $request)
    {
        try {
            // Normalize day to capitalize first letter BEFORE validation (database expects "Monday" not "monday")
            $request->merge(['day' => ucfirst(strtolower($request->day))]);

            $validator = Validator::make($request->all(), [
                'class_subject_teacher_id' => 'required|exists:class_subject_teacher,id',
                'day' => [
                    'required',
                    'string',
                    Rule::in(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
                ],
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'room' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if assignment exists
            $assignment = ClassSubjectTeacher::with(['class', 'subject', 'teacher'])->find($request->class_subject_teacher_id);
            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.assignment_not_found')
                ], 404);
            }

            // Check for conflicts (same teacher, same time, same day)
            $hasTeacherConflict = $this->checkTeacherConflict(
                $assignment->teacher_id,
                $request->day,
                $request->start_time,
                $request->end_time
            );

            if ($hasTeacherConflict) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.teacher_schedule_conflict'),
                    'conflict' => 'teacher_time_conflict'
                ], 409);
            }

            // Check for classroom conflict
            if ($request->room) {
                $hasRoomConflict = $this->checkRoomConflict(
                    $request->room,
                    $request->day,
                    $request->start_time,
                    $request->end_time
                );

                if ($hasRoomConflict) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.room_conflict'),
                        'conflict' => 'room_conflict'
                    ], 409);
                }
            }

            // Check for class conflict
            $hasClassConflict = $this->checkClassConflict(
                $assignment->class_id,
                $request->day,
                $request->start_time,
                $request->end_time
            );

            if ($hasClassConflict) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.class_schedule_conflict'),
                    'conflict' => 'class_time_conflict'
                ], 409);
            }

            $schedule = Schedule::create($request->all());
            $schedule->load(['assignment.class', 'assignment.subject', 'assignment.teacher']);

            return response()->json([
                'success' => true,
                'message' => __('messages.schedule_created'),
                'data' => $schedule
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_create_schedule'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified schedule.
     */
    public function show(string $id)
    {
        try {
            $schedule = Schedule::with(['assignment.class', 'assignment.subject', 'assignment.teacher'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $schedule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.schedule_not_found'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Update the specified schedule.
     */
    public function update(Request $request, string $id)
    {
        try {
            $schedule = Schedule::findOrFail($id);

            // Normalize day to capitalize first letter if provided (database expects "Monday" not "monday")
            if ($request->has('day')) {
                $request->merge(['day' => ucfirst(strtolower($request->day))]);
            }

            $validator = Validator::make($request->all(), [
                'class_subject_teacher_id' => 'sometimes|exists:class_subject_teacher,id',
                'day' => [
                    'sometimes',
                    'string',
                    Rule::in(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
                ],
                'start_time' => 'sometimes|date_format:H:i',
                'end_time' => 'sometimes|date_format:H:i|after:start_time',
                'room' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get assignment info
            $assignmentId = $request->get('class_subject_teacher_id', $schedule->class_subject_teacher_id);
            $assignment = ClassSubjectTeacher::with(['class', 'subject', 'teacher'])->find($assignmentId);

            // Get updated values
            $day = $request->get('day', $schedule->day);
            $startTime = $request->get('start_time', $schedule->start_time);
            $endTime = $request->get('end_time', $schedule->end_time);
            $room = $request->get('room', $schedule->room);

            // Check for conflicts (exclude current schedule)
            $hasTeacherConflict = $this->checkTeacherConflict(
                $assignment->teacher_id,
                $day,
                $startTime,
                $endTime,
                $id
            );

            if ($hasTeacherConflict) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.teacher_schedule_conflict'),
                    'conflict' => 'teacher_time_conflict'
                ], 409);
            }

            if ($room) {
                $hasRoomConflict = $this->checkRoomConflict(
                    $room,
                    $day,
                    $startTime,
                    $endTime,
                    $id
                );

                if ($hasRoomConflict) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.room_conflict'),
                        'conflict' => 'room_conflict'
                    ], 409);
                }
            }

            $hasClassConflict = $this->checkClassConflict(
                $assignment->class_id,
                $day,
                $startTime,
                $endTime,
                $id
            );

            if ($hasClassConflict) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.class_schedule_conflict'),
                    'conflict' => 'class_time_conflict'
                ], 409);
            }

            $schedule->update($request->all());
            $schedule->load(['assignment.class', 'assignment.subject', 'assignment.teacher']);

            return response()->json([
                'success' => true,
                'message' => __('messages.schedule_updated'),
                'data' => $schedule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_update_schedule'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified schedule.
     */
    public function destroy(string $id)
    {
        try {
            $schedule = Schedule::findOrFail($id);
            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.schedule_deleted')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_delete_schedule'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get schedules for a specific class.
     * Groups schedules by day of the week.
     */
    public function getClassSchedule(Request $request, $classId)
    {
        
        try {
            $academicYear = $request->get('academic_year', date('Y') . '-' . (date('Y') + 1));
            
            \Log::info('getClassSchedule called', [
                'class_id' => $classId,
                'academic_year_param' => $request->get('academic_year'),
                'academic_year_used' => $academicYear,
                'all_params' => $request->all()
            ]);

            $query = Schedule::with(['assignment.subject', 'assignment.teacher'])
                ->whereHas('assignment', function ($q) use ($classId, $academicYear) {
                    $q->where('class_id', $classId)
                      ->where('academic_year', $academicYear);
                });
            
            $this->orderByDayOfWeek($query);
            $schedules = $query->orderBy('start_time')->get();
            
            \Log::info('Schedules found', ['count' => $schedules->count()]);

            // Normalize day to lowercase for frontend compatibility
            $schedules->transform(function ($schedule) {
                $schedule->day = strtolower($schedule->day);
                return $schedule;
            });

            // Group by day
            $groupedSchedules = $schedules->groupBy('day');

            return response()->json([
                'success' => true,
                'data' => $groupedSchedules,
                'total' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_class_schedule'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get schedules for a specific teacher.
     */
    public function getTeacherSchedule(Request $request, $teacherId)
    {
        try {
            $academicYear = $request->get('academic_year', date('Y') . '-' . (date('Y') + 1));

            $query = Schedule::with(['assignment.class', 'assignment.subject'])
                ->whereHas('assignment', function ($q) use ($teacherId, $academicYear) {
                    $q->where('teacher_id', $teacherId)
                      ->where('academic_year', $academicYear);
                });
            
            $this->orderByDayOfWeek($query);
            $schedules = $query->orderBy('start_time')->get();

            // Group by day
            $groupedSchedules = $schedules->groupBy('day');

            return response()->json([
                'success' => true,
                'data' => $groupedSchedules,
                'total' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_teacher_schedule'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get schedules for a specific subject.
     */
    public function getSubjectSchedule($subjectId)
    {
        try {
            $query = Schedule::with(['assignment.class', 'assignment.teacher'])
                ->whereHas('assignment', function ($q) use ($subjectId) {
                    $q->where('subject_id', $subjectId);
                });
            
            $this->orderByDayOfWeek($query);
            $schedules = $query->orderBy('start_time')->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'total' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_subject_schedule'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get schedules for a specific day.
     */
    public function getDaySchedule(Request $request, $day)
    {
        try {
            // Normalize day to capitalize first letter (database expects "Monday" not "monday")
            $day = ucfirst(strtolower($day));
            $validDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            if (!in_array($day, $validDays)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.invalid_day')
                ], 400);
            }

            $query = Schedule::with(['assignment.class', 'assignment.subject', 'assignment.teacher'])
                ->where('day', $day);

            // Optional filters
            if ($request->has('class_id')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('class_id', $request->class_id);
                });
            }

            if ($request->has('teacher_id')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('teacher_id', $request->teacher_id);
                });
            }

            $schedules = $query->orderBy('start_time')->get();

            return response()->json([
                'success' => true,
                'day' => $day,
                'data' => $schedules,
                'total' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_day_schedule'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get room utilization schedule.
     */
    public function getRoomSchedule($room)
    {
        try {
            $query = Schedule::with(['assignment.class', 'assignment.subject', 'assignment.teacher'])
                ->where('room', $room);
            
            $this->orderByDayOfWeek($query);
            $schedules = $query->orderBy('start_time')->get();

            // Group by day
            $groupedSchedules = $schedules->groupBy('day');

            return response()->json([
                'success' => true,
                'room' => $room,
                'data' => $groupedSchedules,
                'total' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_room_schedule'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk create schedules.
     */
    public function bulkStore(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'schedules' => 'required|array|min:1',
                'schedules.*.class_subject_teacher_id' => 'required|exists:class_subject_teacher,id',
                'schedules.*.day' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                        if (!in_array(strtolower($value), $validDays)) {
                            $fail('The selected ' . $attribute . ' is invalid.');
                        }
                    }
                ],
                'schedules.*.start_time' => 'required|date_format:H:i',
                'schedules.*.end_time' => 'required|date_format:H:i',
                'schedules.*.room' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $created = [];
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($request->schedules as $index => $scheduleData) {
                    // Normalize day to capitalize first letter (database expects "Monday" not "monday")
                    $scheduleData['day'] = ucfirst(strtolower($scheduleData['day']));
                    
                    // Validate each schedule
                    $assignment = ClassSubjectTeacher::find($scheduleData['class_subject_teacher_id']);
                    
                    // Check conflicts
                    $conflicts = [];
                    
                    if ($this->checkTeacherConflict(
                        $assignment->teacher_id,
                        $scheduleData['day'],
                        $scheduleData['start_time'],
                        $scheduleData['end_time']
                    )) {
                        $conflicts[] = 'teacher_conflict';
                    }

                    if (isset($scheduleData['room']) && $this->checkRoomConflict(
                        $scheduleData['room'],
                        $scheduleData['day'],
                        $scheduleData['start_time'],
                        $scheduleData['end_time']
                    )) {
                        $conflicts[] = 'room_conflict';
                    }

                    if ($this->checkClassConflict(
                        $assignment->class_id,
                        $scheduleData['day'],
                        $scheduleData['start_time'],
                        $scheduleData['end_time']
                    )) {
                        $conflicts[] = 'class_conflict';
                    }

                    if (!empty($conflicts)) {
                        $errors[] = [
                            'index' => $index,
                            'data' => $scheduleData,
                            'conflicts' => $conflicts
                        ];
                        continue;
                    }

                    $schedule = Schedule::create($scheduleData);
                    $created[] = $schedule;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => __('messages.schedules_created', ['count' => count($created)]),
                    'data' => $created,
                    'errors' => $errors,
                    'summary' => [
                        'total_submitted' => count($request->schedules),
                        'created' => count($created),
                        'failed' => count($errors)
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_create_schedules'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check for schedule conflicts.
     */
    public function checkConflicts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'class_subject_teacher_id' => 'required|exists:class_subject_teacher,id',
                'day' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                        if (!in_array(strtolower($value), $validDays)) {
                            $fail('The selected ' . $attribute . ' is invalid.');
                        }
                    }
                ],
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
                'room' => 'nullable|string',
                'exclude_schedule_id' => 'nullable|exists:schedules,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            // Normalize day to capitalize first letter (database expects "Monday" not "monday")
            $day = ucfirst(strtolower($request->day));

            $assignment = ClassSubjectTeacher::find($request->class_subject_teacher_id);
            $conflicts = [];

            // Check teacher conflict
            if ($this->checkTeacherConflict(
                $assignment->teacher_id,
                $request->day,
                $request->start_time,
                $request->end_time,
                $request->exclude_schedule_id
            )) {
                $conflicts[] = [
                    'type' => 'teacher',
                    'message' => __('messages.teacher_conflict')
                ];
            }

            // Check room conflict
            if ($request->room && $this->checkRoomConflict(
                $request->room,
                $day,
                $request->start_time,
                $request->end_time,
                $request->exclude_schedule_id
            )) {
                $conflicts[] = [
                    'type' => 'room',
                    'message' => __('messages.room_occupied')
                ];
            }

            // Check class conflict
            if ($this->checkClassConflict(
                $assignment->class_id,
                $day,
                $request->start_time,
                $request->end_time,
                $request->exclude_schedule_id
            )) {
                $conflicts[] = [
                    'type' => 'class',
                    'message' => __('messages.class_conflict')
                ];
            }

            return response()->json([
                'success' => true,
                'has_conflicts' => !empty($conflicts),
                'conflicts' => $conflicts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_check_conflicts'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get available time slots for scheduling.
     */
    public function getAvailableSlots(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'teacher_id' => 'nullable|exists:teachers,id',
                'class_id' => 'nullable|exists:classes,id',
                'room' => 'nullable|string',
                'day' => 'required|in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
                'start_hour' => 'nullable|integer|min:0|max:23',
                'end_hour' => 'nullable|integer|min:0|max:23',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $startHour = $request->get('start_hour', 8);
            $endHour = $request->get('end_hour', 18);
            $day = $request->day;

            // Get all occupied slots
            $query = Schedule::where('day', $day)
                ->whereBetween('start_time', [
                    sprintf('%02d:00', $startHour),
                    sprintf('%02d:59', $endHour)
                ]);

            if ($request->teacher_id) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('teacher_id', $request->teacher_id);
                });
            }

            if ($request->class_id) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('class_id', $request->class_id);
                });
            }

            if ($request->room) {
                $query->where('room', $request->room);
            }

            $occupiedSlots = $query->get(['start_time', 'end_time'])->toArray();

            return response()->json([
                'success' => true,
                'day' => $day,
                'time_range' => [
                    'start' => sprintf('%02d:00', $startHour),
                    'end' => sprintf('%02d:00', $endHour)
                ],
                'occupied_slots' => $occupiedSlots,
                'total_occupied' => count($occupiedSlots)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_get_available_slots'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get weekly schedule overview.
     */
    public function getWeeklyOverview(Request $request)
    {
        try {
            $query = Schedule::with(['assignment.class', 'assignment.subject', 'assignment.teacher']);

            if ($request->has('class_id')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('class_id', $request->class_id);
                });
            }

            if ($request->has('teacher_id')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('teacher_id', $request->teacher_id);
                });
            }

            if ($request->has('academic_year')) {
                $query->whereHas('assignment', function ($q) use ($request) {
                    $q->where('academic_year', $request->academic_year);
                });
            }

            $this->orderByDayOfWeek($query);
            $schedules = $query->orderBy('start_time')->get();

            $weeklyOverview = $schedules->groupBy('day');

            // Statistics
            $stats = [
                'total_sessions' => $schedules->count(),
                'days_with_classes' => $weeklyOverview->keys()->count(),
                'sessions_per_day' => $weeklyOverview->map(function ($day) {
                    return $day->count();
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $weeklyOverview,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_get_weekly_overview'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generate schedules for all classes based on existing class-teacher-subject assignments.
     */
    public function generateAll(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'academic_year' => 'required|string',
                'clear_existing' => 'nullable|boolean',
                'save' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $academicYear = $request->academic_year;
            $clearExisting = $request->get('clear_existing', true);
            $shouldSave = $request->get('save', true);

            $assignments = ClassSubjectTeacher::with([
                'class.levelProfile',
                'subject',
                'teacher.availabilities'
            ])->where('academic_year', $academicYear)->get();

            if ($assignments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.no_class_assignments_found')
                ], 422);
            }

            $teacherSessionMap = [];
            $teacherWeeklyLoad = [];
            $classSessionMap = [];
            $classImportantPerDay = [];
            $generatedRows = [];
            $unfilled = [];

            $assignmentDemands = [];
            foreach ($assignments as $assignment) {
                $levelId = $assignment->class?->level_id;
                $levelSubject = LevelSubject::where('level_id', $levelId)
                    ->where('subject_id', $assignment->subject_id)
                    ->first();

                if (!$levelSubject) {
                    $unfilled[] = [
                        'assignment_id' => $assignment->id,
                        'reason' => __('messages.missing_level_subject_config')
                    ];
                    continue;
                }

                $assignmentDemands[] = [
                    'assignment' => $assignment,
                    'weekly_sessions_required' => (int) $levelSubject->weekly_sessions_required,
                    'coefficient' => (int) $levelSubject->coefficient,
                    'is_important' => (int) $levelSubject->coefficient >= 3,
                ];
            }

            if (empty($assignmentDemands)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.no_schedulable_assignments')
                ], 422);
            }

            usort($assignmentDemands, function ($a, $b) {
                $teacherA = $a['assignment']->teacher;
                $teacherB = $b['assignment']->teacher;
                $rankA = ($teacherA && $teacherA->contract_type === 'permanent') ? 0 : 1;
                $rankB = ($teacherB && $teacherB->contract_type === 'permanent') ? 0 : 1;

                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                if ($a['coefficient'] !== $b['coefficient']) {
                    return $b['coefficient'] <=> $a['coefficient'];
                }

                return $a['assignment']->teacher_id <=> $b['assignment']->teacher_id;
            });

            foreach ($assignmentDemands as $demand) {

                $assignment = $demand['assignment'];
                $required = $demand['weekly_sessions_required'];
                $placed = 0;

                // Special handling for Sports (SP) - always schedule as two consecutive hours
                $subject = $assignment->subject;
                if ($subject && (strtoupper($subject->code ?? '') === 'SP') && $required === 2) {
                    $found = false;
                    foreach ($this->generationDays as $day) {
                        foreach ($this->sessionSlots as $i => $slot) {
                            // Check if next slot exists and is consecutive
                            if (!isset($this->sessionSlots[$i + 1])) continue;
                            $slot2 = $this->sessionSlots[$i + 1];
                            // Ensure slots are consecutive
                            if ($slot['end'] !== $slot2['start']) continue;

                            $start1 = $slot['start'];
                            $end1 = $slot['end'];
                            $start2 = $slot2['start'];
                            $end2 = $slot2['end'];

                            $teacherId = $assignment->teacher_id;
                            $classId = $assignment->class_id;

                            // Check teacher and class availability for both slots
                            if (
                                $this->isTeacherAvailableForSlot($assignment->teacher, $day, $start1, $end1) &&
                                $this->isTeacherAvailableForSlot($assignment->teacher, $day, $start2, $end2) &&
                                empty($teacherSessionMap[$teacherId][$day][$start1]) &&
                                empty($teacherSessionMap[$teacherId][$day][$start2]) &&
                                empty($classSessionMap[$classId][$day][$start1]) &&
                                empty($classSessionMap[$classId][$day][$start2]) &&
                                !$this->wouldCreateClassInternalGap($classSessionMap[$classId][$day] ?? [], $start1) &&
                                !$this->wouldCreateClassInternalGap(array_merge($classSessionMap[$classId][$day] ?? [], [$start1 => true]), $start2)
                            ) {
                                // Place both sessions
                                $teacherSessionMap[$teacherId][$day][$start1] = true;
                                $teacherSessionMap[$teacherId][$day][$start2] = true;
                                $teacherWeeklyLoad[$teacherId] = ($teacherWeeklyLoad[$teacherId] ?? 0) + 2;
                                $classSessionMap[$classId][$day][$start1] = true;
                                $classSessionMap[$classId][$day][$start2] = true;

                                if ($demand['is_important']) {
                                    $classImportantPerDay[$classId][$day] = ($classImportantPerDay[$classId][$day] ?? 0) + 2;
                                }

                                $generatedRows[] = [
                                    'class_subject_teacher_id' => $assignment->id,
                                    'day' => $day,
                                    'start_time' => $start1,
                                    'end_time' => $end1,
                                    'room' => null,
                                ];
                                $generatedRows[] = [
                                    'class_subject_teacher_id' => $assignment->id,
                                    'day' => $day,
                                    'start_time' => $start2,
                                    'end_time' => $end2,
                                    'room' => null,
                                ];
                                $placed = 2;
                                $found = true;
                                break 2;
                            }
                        }
                    }
                    // If not found, skip to diagnostics
                } else {
                    // Default: schedule each session individually
                    for ($i = 0; $i < $required; $i++) {
                        $candidate = $this->findBestSlotForDemand(
                            $demand,
                            $teacherSessionMap,
                            $teacherWeeklyLoad,
                            $classSessionMap,
                            $classImportantPerDay
                        );

                        // Last-resort fallback: allow exceeding important-subject daily limit
                        // only when no feasible slot exists under the strict limit.
                        if (!$candidate) {
                            $candidate = $this->findBestSlotForDemand(
                                $demand,
                                $teacherSessionMap,
                                $teacherWeeklyLoad,
                                $classSessionMap,
                                $classImportantPerDay,
                                true
                            );
                        }

                        if (!$candidate) {
                            break;
                        }

                        $teacherId = $assignment->teacher_id;
                        $classId = $assignment->class_id;
                        $day = $candidate['day'];
                        $start = $candidate['start'];
                        $end = $candidate['end'];

                        $teacherSessionMap[$teacherId][$day][$start] = true;
                        $teacherWeeklyLoad[$teacherId] = ($teacherWeeklyLoad[$teacherId] ?? 0) + 1;
                        $classSessionMap[$classId][$day][$start] = true;

                        if ($demand['is_important']) {
                            $classImportantPerDay[$classId][$day] = ($classImportantPerDay[$classId][$day] ?? 0) + 1;
                        }

                        $generatedRows[] = [
                            'class_subject_teacher_id' => $assignment->id,
                            'day' => $day,
                            'start_time' => $start,
                            'end_time' => $end,
                            'room' => null,
                        ];

                        $placed++;
                    }
                }

                if ($placed < $required) {
                    $diagnosis = $this->diagnoseUnfilledReason(
                        $demand,
                        $teacherSessionMap,
                        $teacherWeeklyLoad,
                        $classSessionMap,
                        $classImportantPerDay
                    );

                    $unfilled[] = [
                        'assignment_id' => $assignment->id,
                        'class_id' => $assignment->class_id,
                        'teacher_id' => $assignment->teacher_id,
                        'subject_id' => $assignment->subject_id,
                        'class_name' => $assignment->class?->name,
                        'teacher_name' => trim(($assignment->teacher?->first_name ?? '') . ' ' . ($assignment->teacher?->last_name ?? '')),
                        'subject_name' => $assignment->subject?->name,
                        'required' => $required,
                        'placed' => $placed,
                        'reason' => $diagnosis['message'],
                        'diagnostics' => $diagnosis['counts'],
                    ];
                }
            }

            $savedCount = 0;
            if ($shouldSave) {
                DB::transaction(function () use ($academicYear, $clearExisting, $generatedRows, &$savedCount) {
                    if ($clearExisting) {
                        Schedule::whereHas('assignment', function ($q) use ($academicYear) {
                            $q->where('academic_year', $academicYear);
                        })->delete();
                    }

                    foreach ($generatedRows as $row) {
                        Schedule::create($row);
                        $savedCount++;
                    }
                });
            }

            $teacherWeeklySummary = collect($assignments)
                ->groupBy('teacher_id')
                ->map(function ($teacherAssignments, $teacherId) use ($teacherWeeklyLoad) {
                    $teacher = $teacherAssignments->first()?->teacher;
                    $name = trim(($teacher?->first_name ?? '') . ' ' . ($teacher?->last_name ?? ''));
                    $load = $teacherWeeklyLoad[$teacherId] ?? 0;

                    return [
                        'teacher_id' => (int) $teacherId,
                        'teacher_name' => $name,
                        'weekly_sessions' => $load,
                        'target_sessions' => $this->teacherWeeklyTargetSessions,
                        'target_reached' => $load >= $this->teacherWeeklyTargetSessions,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => $shouldSave ? __('messages.schedules_generated_successfully') : __('messages.schedule_preview_generated'),
                'summary' => [
                    'academic_year' => $academicYear,
                    'generated_sessions' => count($generatedRows),
                    'saved_sessions' => $savedCount,
                    'unfilled_items' => count($unfilled),
                    'clear_existing' => $clearExisting,
                    'saved' => $shouldSave,
                    'teacher_target_sessions' => $this->teacherWeeklyTargetSessions,
                ],
                'teacher_weekly_load' => $teacherWeeklySummary,
                'unfilled' => $unfilled,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_generate_schedules'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export schedules to an Excel-compatible file (.xls HTML table).
     */
    public function exportExcel(Request $request)
    {
        try {
            $academicYear = $request->get('academic_year');

            $query = Schedule::with(['assignment.class', 'assignment.subject', 'assignment.teacher']);
            if ($academicYear) {
                $query->whereHas('assignment', function ($q) use ($academicYear) {
                    $q->where('academic_year', $academicYear);
                });
            }

            $schedules = $query->get();

            $classGroups = $schedules
                ->sortBy([
                    fn ($a, $b) => strcmp((string) ($a->assignment?->class?->name ?? ''), (string) ($b->assignment?->class?->name ?? '')),
                    fn ($a, $b) => strcmp((string) ($a->day ?? ''), (string) ($b->day ?? '')),
                    fn ($a, $b) => strcmp((string) ($a->start_time ?? ''), (string) ($b->start_time ?? '')),
                ])
                ->groupBy(function ($s) {
                    return $s->assignment?->class?->name ?? 'Unassigned Class';
                });

            $teacherGroups = $schedules
                ->sortBy([
                    fn ($a, $b) => strcmp(
                        trim((string) (($a->assignment?->teacher?->first_name ?? '') . ' ' . ($a->assignment?->teacher?->last_name ?? ''))),
                        trim((string) (($b->assignment?->teacher?->first_name ?? '') . ' ' . ($b->assignment?->teacher?->last_name ?? '')))
                    ),
                    fn ($a, $b) => strcmp((string) ($a->day ?? ''), (string) ($b->day ?? '')),
                    fn ($a, $b) => strcmp((string) ($a->start_time ?? ''), (string) ($b->start_time ?? '')),
                ])
                ->groupBy(function ($s) {
                    $name = trim((string) (($s->assignment?->teacher?->first_name ?? '') . ' ' . ($s->assignment?->teacher?->last_name ?? '')));
                    return $name !== '' ? $name : 'Unassigned Teacher';
                });

            $html = '<html><head><meta charset="UTF-8">';
            $html .= '<style>
                body { font-family: Arial, sans-serif; color: #1f2937; }
                h2 { margin: 18px 0 10px; color: #111827; }
                h3 { margin: 14px 0 8px; color: #1f2937; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 18px; table-layout: fixed; }
                th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; font-size: 12px; }
                th { background: #f1f5f9; text-align: center; font-weight: 700; }
                td.time-col { background: #f8fafc; width: 110px; font-weight: 600; text-align: center; }
                .entry { margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px dashed #cbd5e1; }
                .entry:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
                .muted { color: #6b7280; font-size: 11px; }
                .page-break { page-break-before: always; }
            </style>';
            $html .= '</head><body>';

            $html .= '<h2>Schedules by Class</h2>';
            foreach ($classGroups as $className => $group) {
                $html .= '<h3>' . e((string) $className) . '</h3>';
                $html .= $this->buildScheduleGrid($group->values()->all(), 'class');
            }

            $html .= '<div class="page-break"></div><h2>Schedules by Teacher</h2>';
            foreach ($teacherGroups as $teacherName => $group) {
                $html .= '<h3>' . e((string) $teacherName) . '</h3>';
                $html .= $this->buildScheduleGrid($group->values()->all(), 'teacher');
            }

            $html .= '</body></html>';

            $fileName = 'schedules_' . ($academicYear ?: 'all') . '.xls';

            return response($html, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export schedules.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function buildScheduleGrid(array $schedules, string $mode = 'class'): string
    {
        if (empty($schedules)) {
            return '<p>No data available.</p>';
        }

        $days = $this->resolveExportDays($schedules);

        $grid = [];
        foreach ($this->sessionSlots as $slot) {
            $start = $slot['start'];
            foreach ($days as $day) {
                $grid[$start][$day] = [];
            }
        }

        foreach ($schedules as $schedule) {
            $rawDay = (string) ($schedule->day ?? '');
            $day = $this->normalizeExportDay($rawDay);
            $start = $this->normalizeExportTime($schedule->start_time ?? null);

            if ($start === null || !isset($grid[$start])) {
                continue;
            }

            if (!isset($grid[$start][$day])) {
                $matchedDay = $this->findMatchingDayKey(array_keys($grid[$start]), $rawDay);
                if ($matchedDay === null) {
                    continue;
                }
                $day = $matchedDay;
            }

            $grid[$start][$day][] = $schedule;
        }

        $html = '<table><thead><tr><th>Time</th>';
        foreach ($days as $day) {
            $html .= '<th>' . e($day) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($this->sessionSlots as $slot) {
            $start = $slot['start'];
            $end = $slot['end'];

            $html .= '<tr>';
            $html .= '<td class="time-col">' . e($start . ' - ' . $end) . '</td>';

            foreach ($days as $day) {
                $entries = $grid[$start][$day] ?? [];
                if (empty($entries)) {
                    $html .= '<td></td>';
                    continue;
                }

                $html .= '<td>';
                foreach ($entries as $entry) {
                    $html .= $this->renderScheduleGridEntry($entry, $mode);
                }
                $html .= '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function resolveExportDays(array $schedules): array
    {
        $orderedDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $daysInSchedules = collect($schedules)
            ->map(function ($s) {
                return $this->normalizeExportDay((string) ($s->day ?? ''));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $selected = array_filter($orderedDays, function ($day) use ($daysInSchedules) {
            return in_array($day, $this->generationDays, true) || in_array($day, $daysInSchedules, true);
        });

        return !empty($selected) ? array_values($selected) : $this->generationDays;
    }

    private function normalizeExportDay(string $day): string
    {
        $normalized = ucfirst(strtolower(trim($day)));

        $validDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return in_array($normalized, $validDays, true) ? $normalized : $normalized;
    }

    private function normalizeExportTime($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Handles plain time strings like 08:00 or 08:00:00.
        if (preg_match('/^(\d{2}:\d{2})/', $raw, $m)) {
            return $m[1];
        }

        // Handles ISO date-time strings like 2026-03-24T08:00:00.000000Z.
        if (preg_match('/T(\d{2}:\d{2})/', $raw, $m)) {
            return $m[1];
        }

        try {
            return Carbon::parse($raw)->format('H:i');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function findMatchingDayKey(array $availableDays, string $rawDay): ?string
    {
        $needle = strtolower(trim($rawDay));
        if ($needle === '') {
            return null;
        }

        foreach ($availableDays as $day) {
            if (strtolower((string) $day) === $needle) {
                return (string) $day;
            }
        }

        return null;
    }

    private function renderScheduleGridEntry($schedule, string $mode): string
    {
        $subject = (string) ($schedule->assignment?->subject?->name ?? '');
        $className = (string) ($schedule->assignment?->class?->name ?? '');
        $teacherName = trim((string) (($schedule->assignment?->teacher?->first_name ?? '') . ' ' . ($schedule->assignment?->teacher?->last_name ?? '')));
        $room = (string) ($schedule->room ?? '');

        if ($mode === 'teacher') {
            $lineOne = $className !== '' ? $className : 'Unknown Class';
            $lineTwo = $subject !== '' ? $subject : 'Unknown Subject';
        } else {
            $lineOne = $subject !== '' ? $subject : 'Unknown Subject';
            $lineTwo = $teacherName !== '' ? $teacherName : 'Unknown Teacher';
        }

        $html = '<div class="entry">';
        $html .= '<div><strong>' . e($lineOne) . '</strong></div>';
        $html .= '<div>' . e($lineTwo) . '</div>';
        if ($room !== '') {
            $html .= '<div class="muted">Room: ' . e($room) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    // Helper methods for conflict checking

    private function findBestSlotForDemand(
        array $demand,
        array $teacherSessionMap,
        array $teacherWeeklyLoad,
        array $classSessionMap,
        array $classImportantPerDay,
        bool $allowImportantOverflow = false
    ): ?array
    {
        $assignment = $demand['assignment'];
        $teacher = $assignment->teacher;
        $teacherId = $assignment->teacher_id;
        $classId = $assignment->class_id;

        if (($teacherWeeklyLoad[$teacherId] ?? 0) >= $this->teacherWeeklyTargetSessions) {
            return null;
        }

        $best = null;
        $bestScore = -999999;

        foreach ($this->generationDays as $day) {
            foreach ($this->sessionSlots as $slot) {
                $start = $slot['start'];
                $end = $slot['end'];

                if (!$this->isTeacherAvailableForSlot($teacher, $day, $start, $end)) {
                    continue;
                }

                if (!empty($teacherSessionMap[$teacherId][$day][$start])) {
                    continue;
                }

                if (!empty($classSessionMap[$classId][$day][$start])) {
                    continue;
                }

                if ($this->wouldCreateClassInternalGap($classSessionMap[$classId][$day] ?? [], $start)) {
                    continue;
                }

                $importantLimitReached = $demand['is_important'] && (($classImportantPerDay[$classId][$day] ?? 0) >= 2);
                if ($importantLimitReached && !$allowImportantOverflow) {
                    continue;
                }

                $score = 0;
                if ($demand['is_important']) {
                    $score += $slot['index'] <= 4 ? 50 : -20;
                }

                // Heavy penalty when overflowing important-subject daily limit.
                // It keeps overflow as a true last-choice when fallback mode is active.
                if ($importantLimitReached) {
                    $score -= 120;
                }

                $teacherWeeklyCount = $teacherWeeklyLoad[$teacherId] ?? 0;
                if ($teacherWeeklyCount < $this->teacherWeeklyTargetSessions) {
                    $score += ($this->teacherWeeklyTargetSessions - $teacherWeeklyCount);
                }

                $teacherDailyCount = count($teacherSessionMap[$teacherId][$day] ?? []);
                if ($teacher?->contract_type === 'permanent') {
                    if ($teacherDailyCount < 4) {
                        $score += 10;
                    }
                    if ($teacherDailyCount >= 6) {
                        $score -= 40;
                    }
                } else {
                    if ($teacherDailyCount >= 6) {
                        $score -= 30;
                    }
                }

                $classDailyCount = count($classSessionMap[$classId][$day] ?? []);
                if ($classDailyCount >= 7) {
                    continue;
                }
                $score -= ($classDailyCount * 2);

                $score -= $this->gapPenalty($teacherSessionMap[$teacherId][$day] ?? [], $start);
                $score -= $this->gapPenalty($classSessionMap[$classId][$day] ?? [], $start);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [
                        'day' => $day,
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            }
        }

        return $best;
    }

    private function wouldCreateClassInternalGap(array $existingSlotsByStart, string $candidateStart): bool
    {
        $indices = [];
        foreach (array_keys($existingSlotsByStart) as $start) {
            $index = $this->slotIndex($start);
            if ($index !== null) {
                $indices[] = $index;
            }
        }

        $candidateIndex = $this->slotIndex($candidateStart);
        if ($candidateIndex !== null) {
            $indices[] = $candidateIndex;
        }

        if (count($indices) <= 1) {
            return false;
        }

        // Separate into morning (1-4) and afternoon (5-8) blocks
        $morning   = array_values(array_filter($indices, fn($i) => $i >= 1 && $i <= 4));
        $afternoon = array_values(array_filter($indices, fn($i) => $i >= 5 && $i <= 8));

        sort($morning);
        sort($afternoon);

        // Check morning block: sessions must be contiguous (no holes inside morning)
        for ($i = 1; $i < count($morning); $i++) {
            if ($morning[$i] - $morning[$i - 1] > 1) {
                return true; // gap inside morning block
            }
        }

        // Check afternoon block: sessions must be contiguous (no holes inside afternoon)
        // BUT a lone start at 5, 6, or 7 with nothing before is fine — only gaps
        // BETWEEN two afternoon sessions are forbidden
        for ($i = 1; $i < count($afternoon); $i++) {
            if ($afternoon[$i] - $afternoon[$i - 1] > 1) {
                return true; // gap inside afternoon block e.g. slot 5 then slot 7
            }
        }

        // Cross-block check is intentionally skipped:
        // Morning ending at any slot (1-4) then continuing in afternoon (5-8)
        // is always valid — the 12:00-13:00 lunch break separates them naturally.

        return false;
    }

    private function isTeacherAvailableForSlot($teacher, string $day, string $start, string $end): bool
    {
        if (!$teacher) {
            return false;
        }

        $availabilities = $teacher->availabilities ?? collect();
        if ($availabilities->isEmpty()) {
            // If no availability defined, assume Sunday-Thursday 08:00-16:00.
            return in_array($day, $this->generationDays) && $start >= '08:00' && $end <= '16:00';
        }

        foreach ($availabilities as $availability) {
            if ($availability->day !== $day) {
                continue;
            }

            $aStart = substr((string) $availability->start_time, 0, 5);
            $aEnd = substr((string) $availability->end_time, 0, 5);

            if ($start >= $aStart && $end <= $aEnd) {
                return true;
            }
        }

        return false;
    }

    private function gapPenalty(array $existingSlotsByStart, string $candidateStart): int
    {
        if (empty($existingSlotsByStart)) {
            return 0;
        }

        $candidateIndex = $this->slotIndex($candidateStart);
        if ($candidateIndex === null) {
            return 0;
        }

        $indices = [];
        foreach (array_keys($existingSlotsByStart) as $start) {
            $index = $this->slotIndex($start);
            if ($index !== null) {
                $indices[] = $index;
            }
        }

        if (empty($indices)) {
            return 0;
        }

        sort($indices);
        $closestDistance = min(array_map(fn ($idx) => abs($idx - $candidateIndex), $indices));
        return max(0, ($closestDistance - 1) * 3);
    }

    private function slotIndex(string $start): ?int
    {
        foreach ($this->sessionSlots as $slot) {
            if ($slot['start'] === $start) {
                return $slot['index'];
            }
        }

        return null;
    }

    private function buildHtmlTable(array $rows): string
    {
        if (empty($rows)) {
            return '<p>No data available.</p>';
        }

        $headers = array_keys($rows[0]);
        $html = '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . e($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $html .= '<td>' . e((string) ($row[$header] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function diagnoseUnfilledReason(
        array $demand,
        array $teacherSessionMap,
        array $teacherWeeklyLoad,
        array $classSessionMap,
        array $classImportantPerDay
    ): array
    {
        $assignment = $demand['assignment'];
        $teacher = $assignment->teacher;
        $teacherId = $assignment->teacher_id;
        $classId = $assignment->class_id;

        $counts = [
            'teacher_weekly_target_reached' => 0,
            'outside_teacher_availability' => 0,
            'teacher_slot_conflict' => 0,
            'class_slot_conflict' => 0,
            'class_gap_constraint' => 0,
            'important_subject_daily_limit' => 0,
            'class_day_capacity_reached' => 0,
            'candidate_slots' => 0,
        ];

        if (($teacherWeeklyLoad[$teacherId] ?? 0) >= $this->teacherWeeklyTargetSessions) {
            return [
                'message' => __('messages.teacher_weekly_target_reached', ['target' => $this->teacherWeeklyTargetSessions]),
                'counts' => array_merge($counts, [
                    'teacher_weekly_target_reached' => count($this->generationDays) * count($this->sessionSlots),
                ]),
            ];
        }

        foreach ($this->generationDays as $day) {
            foreach ($this->sessionSlots as $slot) {
                $start = $slot['start'];
                $end = $slot['end'];

                if (!$this->isTeacherAvailableForSlot($teacher, $day, $start, $end)) {
                    $counts['outside_teacher_availability']++;
                    continue;
                }

                if (!empty($teacherSessionMap[$teacherId][$day][$start])) {
                    $counts['teacher_slot_conflict']++;
                    continue;
                }

                if (!empty($classSessionMap[$classId][$day][$start])) {
                    $counts['class_slot_conflict']++;
                    continue;
                }

                if ($this->wouldCreateClassInternalGap($classSessionMap[$classId][$day] ?? [], $start)) {
                    $counts['class_gap_constraint']++;
                    continue;
                }

                if ($demand['is_important'] && (($classImportantPerDay[$classId][$day] ?? 0) >= 2)) {
                    $counts['important_subject_daily_limit']++;
                    continue;
                }

                $classDailyCount = count($classSessionMap[$classId][$day] ?? []);
                if ($classDailyCount >= 7) {
                    $counts['class_day_capacity_reached']++;
                    continue;
                }

                $counts['candidate_slots']++;
            }
        }

        if ($counts['candidate_slots'] > 0) {
            return [
                'message' => __('messages.no_optimal_slot_found'),
                'counts' => $counts,
            ];
        }

        $map = [
            'teacher_weekly_target_reached' => __('messages.teacher_weekly_target_reached', ['target' => $this->teacherWeeklyTargetSessions]),
            'outside_teacher_availability' => __('messages.outside_teacher_availability'),
            'teacher_slot_conflict' => __('messages.teacher_slot_conflict'),
            'class_slot_conflict' => __('messages.class_slot_conflict'),
            'class_gap_constraint' => __('messages.class_gap_constraint'),
            'important_subject_daily_limit' => __('messages.important_subject_daily_limit'),
            'class_day_capacity_reached' => __('messages.class_day_capacity_reached'),
        ];

        $topKey = 'outside_teacher_availability';
        $topValue = -1;
        foreach ($map as $key => $message) {
            if (($counts[$key] ?? 0) > $topValue) {
                $topValue = $counts[$key];
                $topKey = $key;
            }
        }

        return [
            'message' => $map[$topKey] ?? 'Not enough available slots under current constraints.',
            'counts' => $counts,
        ];
    }

    private function checkTeacherConflict($teacherId, $day, $startTime, $endTime, $excludeId = null)
    {
        $query = Schedule::whereHas('assignment', function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->where('day', $day)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($q2) use ($startTime, $endTime) {
                    $q2->where('start_time', '<', $endTime)
                       ->where('end_time', '>', $startTime);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function checkRoomConflict($room, $day, $startTime, $endTime, $excludeId = null)
    {
        $query = Schedule::where('room', $room)
            ->where('day', $day)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($q2) use ($startTime, $endTime) {
                    $q2->where('start_time', '<', $endTime)
                       ->where('end_time', '>', $startTime);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function checkClassConflict($classId, $day, $startTime, $endTime, $excludeId = null)
    {
        $query = Schedule::whereHas('assignment', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->where('day', $day)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($q2) use ($startTime, $endTime) {
                    $q2->where('start_time', '<', $endTime)
                       ->where('end_time', '>', $startTime);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Helper method to order schedules by day in correct week order.
     * Works with both MySQL and SQLite.
     */
    private function orderByDayOfWeek($query, $direction = 'asc')
    {
        // Use CASE WHEN for SQLite compatibility instead of FIELD()
        if (config('database.default') === 'sqlite') {
            return $query->orderByRaw("CASE day 
                WHEN 'Sunday' THEN 1 
                WHEN 'Monday' THEN 2 
                WHEN 'Tuesday' THEN 3 
                WHEN 'Wednesday' THEN 4 
                WHEN 'Thursday' THEN 5 
                WHEN 'Friday' THEN 6 
                WHEN 'Saturday' THEN 7 
                ELSE 8 END " . $direction);
        } else {
            return $query->orderByRaw("FIELD(day, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') " . $direction);
        }
    }
}
