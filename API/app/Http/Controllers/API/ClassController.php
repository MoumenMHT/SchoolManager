<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $classes = SchoolClass::with(['mainTeacher', 'students', 'teachers', 'subjects'])
                ->get()
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
                        'level' => $class->level,
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
                'message' => 'Failed to retrieve classes',
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
                'name' => 'required|string|max:255|unique:classes,name',
                'level' => 'required|string|max:255',
                'academic_year' => 'nullable|string|max:255',
                'capacity' => 'nullable|integer|min:1',
                'main_teacher_id' => 'nullable|exists:teachers,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $class = SchoolClass::create([
                'name' => $request->name,
                'level' => $request->level,
                'academic_year' => $request->academic_year,
                'capacity' => $request->capacity,
                'is_active' => true,
                'main_teacher_id' => $request->main_teacher_id,
            ]);

            $class->load('mainTeacher');

            return response()->json([
                'success' => true,
                'message' => 'Class created successfully',
                'data' => $class
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create class',
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
            $class = SchoolClass::with(['mainTeacher', 'students', 'teachers', 'subjects'])->find($id);
            
            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Class not found'
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
                'level' => $class->level,
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
                'message' => 'Failed to retrieve class',
                'error' => $e->getMessage()
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
                    'message' => 'Class not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:classes,name,' . $id,
                'level' => 'sometimes|required|string|max:255',
                'academic_year' => 'sometimes|nullable|string|max:255',
                'capacity' => 'sometimes|nullable|integer|min:1',
                'main_teacher_id' => 'nullable|exists:teachers,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $class->update($request->only([
                'name',
                'level',
                'academic_year',
                'capacity',
                'main_teacher_id'
            ]));

            $class->load('mainTeacher');
            
            return response()->json([
                'success' => true,
                'message' => 'Class updated successfully',
                'data' => $class
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update class',
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
            $class = SchoolClass::find($id);
            
            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Class not found'
                ], 404);
            }

            // Check if class has students
            if ($class->students()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete class with enrolled students'
                ], 409);
            }

            $class->delete();

            return response()->json([
                'success' => true,
                'message' => 'Class deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete class',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * remove student from a class
     */

    public function removeStudentFromClass($studentId){
        

        $student = Student::findOrFail($studentId);
        $student->class_id = null;
        $student->save();

        return response()->json([
            'success' => true,
            'message' => 'Student removed from class successfully',
            'data' => $student
        ]);

    }
}
