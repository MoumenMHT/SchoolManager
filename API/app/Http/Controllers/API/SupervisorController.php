<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\ClassSubjectTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SupervisorController extends Controller
{
    // ─── Admin CRUD ───────────────────────────────────────────────

    public function index()
    {
        try {
            $query = Supervisor::with(['user', 'classes']);

            $user = auth()->user();
            if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
                $directorCycle = $user->directorCycle();
                $query->whereHas('classes.levelProfile', function ($q) use ($directorCycle) {
                    $q->where('cycle', $directorCycle);
                });
            }

            $supervisors = $query->get();
            return response()->json(['success' => true, 'data' => $supervisors]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load supervisors',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|numeric',
            'hire_date' => 'nullable|date',
            'status' => 'in:active,inactive',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:8',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Check for already-assigned classes
        if ($request->has('class_ids') && is_array($request->class_ids)) {
            $assignedClasses = SchoolClass::whereIn('id', $request->class_ids)
                ->whereNotNull('supervisor_id')
                ->get();

            if ($assignedClasses->isNotEmpty()) {
                $classNames = $assignedClasses->pluck('name')->join(', ');
                return response()->json([
                    'success' => false,
                    'message' => __('messages.class_already_assigned', ['classes' => $classNames]),
                ], 422);
            }
        }

        try {
            return DB::transaction(function () use ($request) {
                // Create user account
                $user = User::create([
                    'username' => $request->username,
                    'password' => Hash::make($request->password),
                    'role' => 'supervisor',
                    'phone' => $request->phone,
                    'is_active' => true,
                ]);

                // Create supervisor record
                $supervisor = Supervisor::create([
                    'user_id' => $user->id,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'phone' => $request->phone,
                    'hire_date' => $request->hire_date,
                    'status' => $request->status ?? 'active',
                ]);

                // Assign classes
                if ($request->has('class_ids') && is_array($request->class_ids)) {
                    SchoolClass::whereIn('id', $request->class_ids)
                        ->update(['supervisor_id' => $supervisor->id]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Supervisor created successfully',
                    'data' => $supervisor->load(['user', 'classes'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create supervisor',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $supervisor = Supervisor::with(['user', 'classes.students'])->findOrFail($id);
            return response()->json(['success' => true, 'data' => $supervisor]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supervisor not found',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $supervisor = Supervisor::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'phone' => 'nullable|numeric',
                'hire_date' => 'nullable|date',
                'status' => 'in:active,inactive',
                'username' => 'sometimes|required|string|unique:users,username,' . $supervisor->user_id,
                'password' => 'nullable|string|min:8',
                'class_ids' => 'nullable|array',
                'class_ids.*' => 'exists:classes,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            // Check for already-assigned classes (excluding classes already owned by this supervisor)
            if ($request->has('class_ids') && is_array($request->class_ids)) {
                $assignedClasses = SchoolClass::whereIn('id', $request->class_ids)
                    ->whereNotNull('supervisor_id')
                    ->where('supervisor_id', '!=', $supervisor->id)
                    ->get();

                if ($assignedClasses->isNotEmpty()) {
                    $classNames = $assignedClasses->pluck('name')->join(', ');
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.class_already_assigned', ['classes' => $classNames]),
                    ], 422);
                }
            }

            return DB::transaction(function () use ($request, $supervisor) {
                $supervisor->update($request->only(['first_name', 'last_name', 'phone', 'hire_date', 'status']));

                // Update user account
                if ($supervisor->user_id) {
                    $userData = [];
                    if ($request->has('username')) {
                        $userData['username'] = $request->username;
                    }
                    if ($request->has('phone')) {
                        $userData['phone'] = $request->phone;
                    }
                    if ($request->filled('password')) {
                        $userData['password'] = Hash::make($request->password);
                    }
                    if (!empty($userData)) {
                        User::where('id', $supervisor->user_id)->update($userData);
                    }
                }

                // Update class assignments
                if ($request->has('class_ids')) {
                    // Remove supervisor from all currently assigned classes
                    SchoolClass::where('supervisor_id', $supervisor->id)
                        ->update(['supervisor_id' => null]);

                    // Assign new classes
                    if (is_array($request->class_ids) && count($request->class_ids) > 0) {
                        SchoolClass::whereIn('id', $request->class_ids)
                            ->update(['supervisor_id' => $supervisor->id]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Supervisor updated successfully',
                    'data' => $supervisor->fresh(['user', 'classes'])
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update supervisor',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $supervisor = Supervisor::findOrFail($id);

            // Remove supervisor from classes
            SchoolClass::where('supervisor_id', $supervisor->id)
                ->update(['supervisor_id' => null]);

            // Delete user account
            if ($supervisor->user_id) {
                User::destroy($supervisor->user_id);
            }

            $supervisor->delete();

            return response()->json(['success' => true, 'message' => 'Supervisor deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete supervisor',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // ─── Supervisor Portal Endpoints ──────────────────────────────

    /**
     * Get classes assigned to the logged-in supervisor with students
     */
    public function myClasses(Request $request)
    {
        try {
            $user = $request->user();
            $supervisor = $user->supervisor;

            if (!$supervisor) {
                return response()->json(['success' => false, 'message' => 'No supervisor profile linked'], 404);
            }

            $classes = SchoolClass::where('supervisor_id', $supervisor->id)
                ->with(['students' => function ($q) {
                    $q->where('is_active', true)->orderBy('last_name');
                }, 'levelProfile'])
                ->get();

            $classIds = $classes->pluck('id');

            $assignments = ClassSubjectTeacher::with(['subject', 'teacher'])
                ->whereIn('class_id', $classIds)
                ->get()
                ->groupBy('class_id');

            $classes = $classes->map(function ($class) use ($assignments) {
                $classAssignments = $assignments->get($class->id, collect());
                $subjects = $classAssignments
                    ->map(fn($a) => $a->subject)
                    ->filter()
                    ->unique('id')
                    ->values();

                $classData = $class->toArray();
                $classData['subjects'] = $subjects;
                $classData['teacher_assignments'] = $classAssignments->values();
                $classData['students_count'] = $class->students->count();

                return $classData;
            })->values();

            return response()->json(['success' => true, 'data' => $classes]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load classes',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get today's attendance dashboard for supervisor's classes
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            $supervisor = $user->supervisor;

            if (!$supervisor) {
                return response()->json(['success' => false, 'message' => 'No supervisor profile linked'], 404);
            }

            $today = now()->format('Y-m-d');

            $classes = SchoolClass::where('supervisor_id', $supervisor->id)
                ->with(['students' => function ($q) {
                    $q->where('is_active', true);
                }])
                ->get();

            $classData = [];
            foreach ($classes as $class) {
                $studentIds = $class->students->pluck('id');

                $attendances = Attendance::with(['subject', 'schedule.assignment.subject', 'schedule.assignment.teacher'])
                    ->whereIn('student_id', $studentIds)
                    ->whereDate('date', $today)
                    ->get();

                $absent = $attendances->where('status', 'absent')->count();
                $late = $attendances->where('status', 'late')->count();
                $present = $attendances->where('status', 'present')->count();
                $excused = $attendances->where('status', 'excused')->count();
                $totalStudents = $class->students->count();
                $marked = $attendances->count();

                $classData[] = [
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                    'level' => $class->level,
                    'total_students' => $totalStudents,
                    'marked' => $marked,
                    'absent' => $absent,
                    'late' => $late,
                    'present' => $present,
                    'excused' => $excused,
                    'students' => $class->students,
                    'attendances' => $attendances,
                ];
            }

            return response()->json(['success' => true, 'data' => $classData]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get today's schedule for a specific class
     */
    public function classScheduleToday(Request $request, $classId)
    {
        try {
            $user = $request->user();
            $supervisor = $user->supervisor;

            if (!$supervisor) {
                return response()->json(['success' => false, 'message' => 'No supervisor profile linked'], 404);
            }

            // Verify the class belongs to this supervisor
            $class = SchoolClass::where('id', $classId)
                ->where('supervisor_id', $supervisor->id)
                ->firstOrFail();

            $dayName = now()->format('l'); // e.g. "Wednesday"

            $schedules = Schedule::whereHas('assignment', function ($q) use ($classId) {
                    $q->where('class_id', $classId);
                })
                ->where('day', $dayName)
                ->with(['assignment.subject', 'assignment.teacher'])
                ->orderBy('start_time')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'class' => $class,
                    'day' => $dayName,
                    'schedules' => $schedules,
                    'current_time' => now()->format('H:i'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load schedule',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
