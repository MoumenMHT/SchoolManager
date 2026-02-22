<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\ClassModel;
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
                'message' => 'Failed to retrieve attendance records',
                'error' => $e->getMessage()
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
                'status' => 'required|in:present,absent,late,excused',
                'reason' => 'nullable|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendance = Attendance::create([
                'student_id' => $request->student_id,
                'subject_id' => $request->subject_id,
                'teacher_id' => $request->teacher_id,
                'date' => now()->format('Y-m-d'),
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
                'message' => 'Failed to create attendance record',
                'error' => $e->getMessage()
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
                'message' => 'Failed to retrieve attendance record',
                'error' => $e->getMessage()
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
                    'message' => 'Validation failed',
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
                'message' => 'Failed to update attendance record',
                'error' => $e->getMessage()
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
                'message' => 'Attendance record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //get attandence of a student
    public function getAttendanceByStudent($studentId, Request $request)
    {
        try {
            $attendances = Attendance::with(['subject', 'teacher'])
                ->where('student_id', $studentId)
                ->get();


            //filter attendances by  academic year
            if ($request->has('academic_year')) {
                $attendances = $attendances->filter(function ($attendance) use ($request) {
                    return $attendance->student && $attendance->student->class && $attendance->student->class->academic_year == $request->academic_year;
                });
            }

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

            
        
            
            return response()->json([
                'success' => true,
                'data' => $attendances
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records for student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAttendanceByClass($classId, Request $request)
    {
        try {
            // Get attendances for students in this class
            $attendances = Attendance::with(['student', 'teacher'])
                ->whereHas('student', function ($query) use ($classId) {
                    $query->where('class_id', $classId);
                })
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

            return response()->json([
                'success' => true,
                'data' => $attendances
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records for class',
                'error' => $e->getMessage()
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
                'message' => 'Failed to retrieve attendance records for teacher',
                'error' => $e->getMessage()
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
                'message' => 'Failed to retrieve attendance records for subject',
                'error' => $e->getMessage()
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

}
