<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\ClassSubjectTeacher;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class scheduleController extends Controller
{
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
                'error' => $e->getMessage()
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
                    Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
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
                    Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
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
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
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
                        $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
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
                'error' => $e->getMessage()
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
                        $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
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
                'error' => $e->getMessage()
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
                'day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods for conflict checking

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
                WHEN 'Monday' THEN 1 
                WHEN 'Tuesday' THEN 2 
                WHEN 'Wednesday' THEN 3 
                WHEN 'Thursday' THEN 4 
                WHEN 'Friday' THEN 5 
                WHEN 'Saturday' THEN 6 
                WHEN 'Sunday' THEN 7 
                ELSE 8 END " . $direction);
        } else {
            return $query->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') " . $direction);
        }
    }
}
