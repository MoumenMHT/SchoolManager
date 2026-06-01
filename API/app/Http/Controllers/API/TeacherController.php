<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Teacher;
use App\Models\ClassSubjectTeacher;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Teacher::query();

        $user = auth()->user();
        if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
            $directorCycle = $user->directorCycle();
            $query->whereHas('classes.levelProfile', function($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }

        $teachers = $query->get();
        $teachers->makeVisible(['cin', 'salary']);
        $teachers->load('user:id,email,phone'); // Load email and phone fields from the related user
        $teachers->load('teachableSubjects'); // Load subjects relationship
        $teachers->load('availabilities');
        $teachers->loadCount('classes'); // Load count of related classes
        $teachers->load('classes'); // Load count of related students

        $teachers->each(function ($teacher) {
            $teacher->email = $teacher->user ? $teacher->user->email : null; // Add email attribute to teacher
            $teacher->phone = $teacher->user ? $teacher->user->phone : null; // Add phone attribute to teacher
            unset($teacher->user); // Remove the user relationship to avoid confusion
            $teacher->subjects = $teacher->teachableSubjects; // Add subjects to the teacher object
            unset($teacher->teachableSubjects); // Remove the original relationship name
            $teacher->classes = $teacher->classes->pluck('name'); // Add classes to the teacher object
        });
        return response()->json([
            'success' => true,
            'data' => $teachers
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'hire_date' => 'required|date',
            'cin' => 'required|string|max:20',
            'specialization' => 'required|string|max:255',
            'salary' => 'required|numeric|min:0',
            'contract_type' => 'sometimes|in:permanent,part_time',
            'weekly_hours' => 'sometimes|integer|min:1|max:60',
            'availabilities' => 'sometimes|array',
            'availabilities.*.day' => 'required_with:availabilities|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'availabilities.*.start_time' => 'required_with:availabilities|date_format:H:i',
            'availabilities.*.end_time' => 'required_with:availabilities|date_format:H:i|after:availabilities.*.start_time',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $teacher = Teacher::where($request->only('first_name', 'last_name'))->first();

        if ($teacher) {
            return response()->json([
                'success' => false,
                'message' => __('messages.teacher_name_exists')
            ], 409);
        }
        if ($request->cin) {
            $existingTeacher = Teacher::where('cin', $request->cin)->first();
            if ($existingTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.teacher_cin_exists')
                ], 409);
            }
        }


        $teacher = DB::transaction(function () use ($request) {
            $teacher = Teacher::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'birth_date' => $request->birth_date,
                'hire_date' => $request->hire_date,
                'specialization' => $request->specialization,
                'salary' => $request->salary,
                'cin' => $request->cin,
                'contract_type' => $request->contract_type ?? 'permanent',
                'weekly_hours' => $request->weekly_hours ?? 20,
            ]);

            if ($request->has('availabilities')) {
                $this->syncAvailabilities($teacher, $request->input('availabilities', []));
            }

            return $teacher;
        });

        $teacher->load('availabilities');
        $teacher->makeVisible(['cin', 'salary']);

        return response()->json([
            'success' => true,
            'data' => $teacher
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => __('messages.teacher_not_found')
            ], 404);
        }

        $teacher->makeVisible(['cin', 'salary']);
        $teacher->load('user:id,email,phone'); // Load email and phone fields from the related user
        $teacher->load('teachableSubjects'); // Load subjects relationship
        $teacher->load('availabilities');
        $teacher->loadCount('classes'); // Load count of related classes
        $teacher->load('classes'); // Load count of related students
        
        return response()->json([
            'success' => true,
            'data' => $teacher
        ]);
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
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'birth_date' => 'sometimes|required|date',
            'hire_date' => 'sometimes|required|date',
            'specialization' => 'sometimes|required|string|max:255',
            'salary' => 'sometimes|required|numeric|min:0',
            'cin' => 'sometimes|required|string|max:20',
            'contract_type' => 'sometimes|in:permanent,part_time',
            'weekly_hours' => 'sometimes|integer|min:1|max:60',
            'availabilities' => 'sometimes|array',
            'availabilities.*.day' => 'required_with:availabilities|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'availabilities.*.start_time' => 'required_with:availabilities|date_format:H:i',
            'availabilities.*.end_time' => 'required_with:availabilities|date_format:H:i|after:availabilities.*.start_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => __('messages.teacher_not_found')
            ], 404);
        }

        if ($request->first_name && $request->last_name) {
            $existingTeacher = Teacher::where($request->only('first_name', 'last_name'))
                ->where('id', '!=', $teacher->id)
                ->first();

            if ($existingTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.teacher_name_exists')
                ], 409);
            }
        }
        if ($request->cin) {
            $existingTeacher = Teacher::where('cin', $request->cin)
                ->where('id', '!=', $teacher->id)
                ->first();
            if ($existingTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.teacher_cin_exists')
                ], 409);
            }
        }


        DB::transaction(function () use ($request, $teacher) {
            $teacher->update($request->only([
                'first_name',
                'last_name',
                'birth_date',
                'hire_date',
                'specialization',
                'cin',
                'salary',
                'contract_type',
                'weekly_hours'
            ]));

            if ($request->has('availabilities')) {
                $this->syncAvailabilities($teacher, $request->input('availabilities', []));
            }
        });

        $teacher->load('availabilities');
        $teacher->makeVisible(['cin', 'salary']);

        return response()->json([
            'success' => true,
            'data' => $teacher
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => __('messages.teacher_not_found')
            ], 404);
        }

        $teacher->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.teacher_deleted')
        ]);
    }

    /**
     * Get the authenticated teacher's classes with their subjects for each class.
     * GET /teacher/classes
     */
    public function myClasses(Request $request)
    {
        $user = $request->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => __('messages.teacher_profile_not_found')
            ], 404);
        }

        $assignments = ClassSubjectTeacher::with(['class.students', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->get();

        $classes = $assignments->groupBy('class_id')->map(function ($classAssignments) {
            $class = $classAssignments->first()->class;
            if (!$class) return null;

            $classData = $class->toArray();
            $classData['subjects'] = $classAssignments->map(fn($a) => $a->subject)->filter()->values();
            $classData['students_count'] = $class->students->count();
            $classData['students'] = $class->students->values();

            return $classData;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $classes
        ]);
    }

    /**
     * Get all students in the authenticated teacher's classes.
     * GET /teacher/students
     */
    public function myStudents(Request $request)
    {
        $user = $request->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => __('messages.teacher_profile_not_found')
            ], 404);
        }

        $classIds = ClassSubjectTeacher::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->unique();

        $students = Student::whereIn('class_id', $classIds)
            ->with('class:id,name,level')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $students
        ]);
    }

    private function syncAvailabilities(Teacher $teacher, array $availabilities): void
    {
        $teacher->availabilities()->delete();

        if (empty($availabilities)) {
            return;
        }

        $rows = [];
        foreach ($availabilities as $availability) {
            if (empty($availability['day']) || empty($availability['start_time']) || empty($availability['end_time'])) {
                continue;
            }

            $rows[] = [
                'day' => $availability['day'],
                'start_time' => $availability['start_time'],
                'end_time' => $availability['end_time'],
            ];
        }

        if (!empty($rows)) {
            $teacher->availabilities()->createMany($rows);
        }
    }
}
