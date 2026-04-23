<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\StudentHistory;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     * Can be filtered by class_id: GET /api/students?class_id={id}
     */
    public function index(Request $request)
    {
        $query = Student::with('class','parent');
        
        $user = auth()->user();
        if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
            $directorCycle = $user->directorCycle();
            $query->whereHas('class.levelProfile', function($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }
        
        // Filter by class_id if provided
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        
        $students = $query->get();
        
        return response()->json([
            'success' => true,
            'data' => $students
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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:students,code',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'class_id' => 'sometimes|exists:classes,id',
            'parent_id' => 'required|exists:parents,id',
            'enrollment_date' => 'required|date',
            'medical_info' => 'nullable|string',

        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student = Student::where($request->only('first_name', 'last_name'))->first();

        if ($student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_name_exists')
            ], 409);
        }
        $student = Student::where($request->only('code'))->first();
        if ($student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_code_exists')
            ], 409);
        }



        $student = Student::create([
            'user_id' => auth()->id(),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'birth_date' => $request->birth_date,
            'enrollment_date' => $request->enrollment_date,
            'class_id' => $request->class_id,
            'code' => $request->code,
            'gender' => $request->gender,
            'parent_id' => $request->parent_id,
            'medical_info' => $request->medical_info,
            'is_active' => true,
        ]);

        if ($student->class_id) {
            $class = SchoolClass::find($student->class_id);
            if ($class) {
                StudentHistory::create([
                    'student_id' => $student->id,
                    'class_id'   => $student->class_id,
                    'academic_year' => $class->academic_year ?? date('Y') . '-' . (date('Y') + 1),
                    'enrolled_at'   => $student->enrollment_date
                        ? $student->enrollment_date->toDateString()
                        : now()->toDateString(),
                    'left_at' => null,
                ]);
            }
        }

        $student->load('class','parent');

        return response()->json([
            'success' => true,
            'data' => $student
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $student = Student::with('class','parent')->find($id);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_not_found')
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $student
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
        $student = Student::find($id);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_not_found')
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:20|unique:students,code,' . $id,
            'birth_date' => 'sometimes|required|date',
            'gender' => 'sometimes|required|in:male,female,other',
            'class_id' => 'nullable|exists:classes,id',
            'parent_id' => 'sometimes|required|exists:parents,id',
            'enrollment_date' => 'sometimes|required|date',
            'medical_info' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldClassId = $student->class_id;

        $student->update($request->only([
            'first_name', 'last_name', 'code', 'birth_date', 'gender',
            'class_id', 'parent_id', 'enrollment_date', 'medical_info', 'is_active'
        ]));

        if ($request->has('class_id') && $request->class_id != $oldClassId) {
            // Close the current open history record
            if ($oldClassId) {
                StudentHistory::where('student_id', $student->id)
                    ->whereNull('left_at')
                    ->update(['left_at' => now()->toDateString()]);
            }

            // Open a new history record if the student is assigned to a class
            if ($request->class_id) {
                $class = SchoolClass::find($request->class_id);
                if ($class) {
                    StudentHistory::create([
                        'student_id'   => $student->id,
                        'class_id'     => $request->class_id,
                        'academic_year' => $class->academic_year ?? date('Y') . '-' . (date('Y') + 1),
                        'enrolled_at'  => now()->toDateString(),
                        'left_at'      => null,
                    ]);
                }
            }
        }

        $student->load('class','parent');

        return response()->json([
            'success' => true,
            'data' => $student
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $student = Student::find($id);
        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_not_found')
            ], 404);
        }

        $student->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.student_deleted')
        ]); 
    }
    
    /**
     * Get children for authenticated parent
     */
    public function myChildren()
    {
        try {
            $user = auth()->user();
            
            // Get parent record
            $parent = $user->parent;
            
            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.parent_profile_not_found')
                ], 404);
            }
            
            // Get students for this parent
            $students = Student::with('class')
                ->where('parent_id', $parent->id)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $students
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_retrieve_children'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function affectStudentToClass(Request $request, string $id)
    {
        try {
            $student = Student::find($id);
            
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.student_not_found')
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'class_id' => 'required|exists:classes,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            $class = SchoolClass::find($request->class_id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.class_not_found')
                ], 404);
            }

            $student->update([
                'class_id' => $request->class_id
            ]);

            return response()->json([
                'success' => true,
                'data' => $student->load('class')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_affect_student'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function studentsWithoutClass()
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'isDirector') && $user->isDirector()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'count' => 0
            ]);
        }

        $students = Student::with('parent')
            ->where(function($query) {
                $query->whereNull('class_id')
                      ->orWhere('class_id', '');
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $students,
            'count' => $students->count()
        ]);
    }

    public function getHistory(string $id)
    {
        $student = Student::find($id);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => __('messages.student_not_found')
            ], 404);
        }

        $history = StudentHistory::with('schoolClass:id,name,level,academic_year')
            ->where('student_id', $id)
            ->orderBy('enrolled_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }
} 