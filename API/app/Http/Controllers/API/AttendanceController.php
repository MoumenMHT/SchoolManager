<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Schedule;
use Illuminate\Support\Facades\Validator;


class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $attendances = Attendance::with(['student', 'subject'])->get();

            return response()->json([
                'success' => true,
                'data' => $attendances
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_attendance'),
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
                'student_id' => 'required|exists:students,id',
                'subject_id' => 'nullable|exists:subjects,id',
                'teacher_id' => 'required|exists:teachers,id',
                'schedule_id' => 'nullable|exists:schedules,id',
                'status' => 'required|in:present,absent,late,excused',
                'date' => 'nullable|date',
                'reason' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendance = Attendance::create([
                'student_id' => $request->student_id,
                'subject_id' => $request->subject_id,
                'teacher_id' => $request->teacher_id,
                'schedule_id' => $request->schedule_id,
                'date' => $request->date ?? now()->format('Y-m-d'),
                'time' => now()->format('H:i:s'),
                'status' => $request->status,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'success' => true,
                'data' => $attendance
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_create_attendance'),
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
            $attendance = Attendance::with(['student', 'subject'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_attendance_record'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $attendance = Attendance::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:present,absent,late,excused',
                'reason' => 'nullable|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendance->update([
                'status' => $request->status,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_update_attendance'),
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
            $attendance = Attendance::findOrFail($id);
            $attendance->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.attendance_deleted')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_delete_attendance'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    //get attandence of a student
    public function getAttendanceByStudent($studentId, Request $request)
    {
        try {
            // Parents can only access attendance for their own children
            if ($request->user()->role === 'parent') {
                $student = Student::find($studentId);
                if (!$student) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.student_not_found')
                    ], 404);
                }
                $parent = $request->user()->parent;
                if (!$parent || $student->parent_id !== $parent->id) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.unauthorized')
                    ], 403);
                }
            }

            $query = Attendance::with(['subject', 'teacher'])
                ->where('student_id', $studentId);

            // DB-level filters
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereDate('date', '>=', $request->start_date)
                      ->whereDate('date', '<=', $request->end_date);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('subject_id')) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('teacher_id')) {
                $query->where('teacher_id', $request->teacher_id);
            }

            $attendances = $query->get();

            //filter attendances by current month
            if ($request->has('month')){
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->date && $attendance->date->month == $request->month;
                });
            }

            //filter attendances by current semester
            if ($request->has('semester')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    if (!$attendance->student || !$attendance->student->class || !$attendance->student->class->academic_year) {
                        return false;
                    }
                    $academicYear = $attendance->student->class->academic_year;
                    $semester = $request->semester;

                    if ($semester == 1) {
                        return in_array($attendance->date->month, [9, 10, 11, 12]) && $academicYear == now()->year;
                    } elseif ($semester == 2) {
                        return in_array($attendance->date->month, [1, 2, 3]) && $academicYear == now()->year;
                    }elseif ($semester == 3) {
                        return in_array($attendance->date->month, [4, 5, 6]) && $academicYear == now()->year;
                    }

                    return false;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $attendances->values()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_student_attendance'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk create or update attendance records in a single transaction.
     * Accepts: { records: [ { student_id, subject_id, teacher_id, schedule_id, date, status, reason } | { id, status, reason } ] }
     */
    public function bulkStore(Request $request)
    {
        $records = $request->input('records', []);

        if (empty($records)) {
            return response()->json(['success' => false, 'message' => __('messages.no_records_provided')], 422);
        }

        try {
            $saved = \Illuminate\Support\Facades\DB::transaction(function () use ($records) {
                $results = [];
                foreach ($records as $record) {
                    if (!empty($record['id'])) {
                        // Update existing
                        $attendance = Attendance::find($record['id']);
                        if ($attendance) {
                            $attendance->update([
                                'status' => $record['status'],
                                'reason' => $record['reason'] ?? null,
                            ]);
                            $results[] = $attendance;
                        }
                    } else {
                        // Create new
                        $results[] = Attendance::create([
                            'student_id'  => $record['student_id'],
                            'subject_id'  => $record['subject_id'] ?? null,
                            'teacher_id'  => $record['teacher_id'],
                            'schedule_id' => $record['schedule_id'] ?? null,
                            'date'        => $record['date'] ?? now()->format('Y-m-d'),
                            'time'        => now()->format('H:i:s'),
                            'status'      => $record['status'],
                            'reason'      => $record['reason'] ?? null,
                        ]);
                    }
                }
                return $results;
            });

            return response()->json(['success' => true, 'data' => $saved]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_save_attendance'),
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function getAttendanceByClass($classId, Request $request)
    {
        try {
            $query = Attendance::with(['student', 'subject', 'teacher'])
                ->whereHas('student', function ($q) use ($classId) {
                    $q->where('class_id', $classId);
                });

            // DB-level filters (fast, avoid Carbon/string comparison bugs)
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereDate('date', '>=', $request->start_date)
                      ->whereDate('date', '<=', $request->end_date);
            }

            if ($request->has('subject_id')) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('schedule_id')) {
                $query->where('schedule_id', $request->schedule_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('teacher_id')) {
                $query->where('teacher_id', $request->teacher_id);
            }

            $attendances = $query->get();

            // Collection-level filters that require loaded relations
            if ($request->has('academic_year')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->student && $attendance->student->class && $attendance->student->class->academic_year == $request->academic_year;
                });
            }

            if ($request->has('month')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->date->month == $request->month;
                });
            }

            if ($request->has('semester')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    if (!$attendance->student || !$attendance->student->class || !$attendance->student->class->academic_year) {
                        return false;
                    }
                    $academicYear = $attendance->student->class->academic_year;
                    $semester = $request->semester;
                    if ($semester == 1) {
                        return in_array($attendance->date->month, [9, 10, 11, 12]) && $academicYear == now()->year;
                    } elseif ($semester == 2) {
                        return in_array($attendance->date->month, [1, 2, 3]) && $academicYear == now()->year;
                    } elseif ($semester == 3) {
                        return in_array($attendance->date->month, [4, 5, 6]) && $academicYear == now()->year;
                    }
                    return false;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $attendances->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_class_attendance'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getAttendanceByTeacher($teacherId, Request $request)
    {
        try {
            $attendances = Attendance::with(['student', 'subject'])
                ->where('teacher_id', $teacherId)
                ->get();

            //filter attendances by  academic year
            if ($request->has('academic_year')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->student && $attendance->student->class && $attendance->student->class->academic_year == $request->academic_year;
                });
            }

            //filter attendances by month
            if ($request->has('month')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->date->month == $request->month;
                });
            }

            //filter attendances by semester
            if ($request->has('semester')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    if (!$attendance->class || !$attendance->class->academic_year) {
                        return false;
                    }
                    $academicYear = $attendance->class->academic_year;
                    $semester = $request->semester;
                    
                    if ($semester == 1) {
                        return in_array($attendance->date->month, [9, 10, 11, 12]) && $academicYear == now()->year;
                    } elseif ($semester == 2) {
                        return in_array($attendance->date->month, [1, 2, 3]) && $academicYear == now()->year;
                    }elseif ($semester == 3) {
                        return in_array($attendance->date->month, [4, 5, 6]) && $academicYear == now()->year;
                    }
                    
                    return false;
                });
            }

             //filter attendances by status
             if ($request->has('status')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->status == $request->status;
                });
            }

             //filter attendances by subject
             if ($request->has('subject_id')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->subject_id == $request->subject_id;
                });
             }

             //filter attendances by date range
             if ($request->has('start_date') && $request->has('end_date')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->date >= $request->start_date && $attendance->date <= $request->end_date;
                });
             }

             //filter attendances by class
             if ($request->has('class_id')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->student && $attendance->student->class_id == $request->class_id;
                });
             }

             

            return response()->json([
                'success' => true,
                'data' => $attendances
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_teacher_attendance'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getAttendanceBySubject($subjectId, Request $request)
    {
        try {
            $attendances = Attendance::with(['student', 'subject', 'teacher'])
                ->where('subject_id', $subjectId)
                ->get();

            //filter attendances by  academic year
            if ($request->has('academic_year')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->student && $attendance->student->class && $attendance->student->class->academic_year == $request->academic_year;
                });
            }

            //filter attendances by month
            if ($request->has('month')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->date->month == $request->month;
                });
            }

            //filter attendances by semester
            if ($request->has('semester')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    if (!$attendance->class || !$attendance->class->academic_year) {
                        return false;
                    }
                    $academicYear = $attendance->class->academic_year;
                    $semester = $request->semester;
                    
                    if ($semester == 1) {
                        return in_array($attendance->date->month, [9, 10, 11, 12]) && $academicYear == now()->year;
                    } elseif ($semester == 2) {
                        return in_array($attendance->date->month, [1, 2, 3]) && $academicYear == now()->year;
                    }elseif ($semester == 3) {
                        return in_array($attendance->date->month, [4, 5, 6]) && $academicYear == now()->year;
                    }
                    
                    return false;
                });
            }

             //filter attendances by status
             if ($request->has('status')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->status == $request->status;
                });
            }

             //filter attendances by teacher
             if ($request->has('teacher_id')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->teacher_id == $request->teacher_id;
                });
             }

             //filter attendances by date range
                if ($request->has('start_date') && $request->has('end_date')) {
                    $attendances = $attendances->filter(function ($attendance) use ($request) {
                        return $attendance->date >= $request->start_date && $attendance->date <= $request->end_date;
                    });
                }

            //filter attendances by class
            if ($request->has('class_id')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->student && $attendance->student->class_id == $request->class_id;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $attendances
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_subject_attendance'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Get attendances for a specific student (alias for parent route)
     */
    public function studentAttendances($student, Request $request)
    {
        return $this->getAttendanceByStudent($student, $request);
    }

    /**
     * Get attendances for a specific schedule slot on a given date.
     * Used by teacher to load existing attendance before marking.
     */
    public function getAttendanceBySchedule($scheduleId, Request $request)
    {
        try {
            $query = Attendance::with(['student', 'subject'])
                ->where('schedule_id', $scheduleId);

            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            }

            if ($request->has('class_id')) {
                $query->whereHas('student', function ($q) use ($request) {
                    $q->where('class_id', $request->class_id);
                });
            }

            $attendances = $query->get();

            return response()->json([
                'success' => true,
                'data' => $attendances
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_schedule_attendance'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

}
