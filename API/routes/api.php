<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\TeacherController;
use App\Http\Controllers\API\ClassController;
use App\Http\Controllers\API\SubjectController;
use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ParentController;
use App\Http\Controllers\API\TeacherSubjectController;
use App\Http\Controllers\API\SubjectCoefficientController;
use App\Http\Controllers\API\ClassSubjectTeacherController;
use App\Http\Controllers\API\ScheduleController;


// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // Admin only routes
    Route::middleware('role:admin')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::apiResource('users', UserController::class);
        Route::apiResource('students', StudentController::class);
        Route::apiResource('teachers', TeacherController::class);
        Route::apiResource('classes', ClassController::class);
        Route::apiResource('subjects', SubjectController::class);
        Route::apiResource('parents', ParentController::class);
        Route::post('/parents/{id}/create-account', [ParentController::class, 'createAccount']);
        Route::apiResource('payments', PaymentController::class);
        
        // Dashboard statistics
        Route::get('/dashboard/stats', [DashboardController::class, 'index']);
        
        // Teacher-Subject Management
        Route::get('/teacher-subjects', [TeacherSubjectController::class, 'index']);
        Route::post('/teacher-subjects', [TeacherSubjectController::class, 'store']);
        Route::post('/teacher-subjects/assign-multiple', [TeacherSubjectController::class, 'assignMultiple']);
        Route::delete('/teacher-subjects/{id}', [TeacherSubjectController::class, 'destroy']);
        Route::post('/teacher-subjects/remove', [TeacherSubjectController::class, 'removeSubjectFromTeacher']);
        Route::get('/teachers/{teacher}/subjects', [TeacherSubjectController::class, 'getTeacherSubjects']);
        Route::get('/subjects/{subject}/teachers', [TeacherSubjectController::class, 'getSubjectTeachers']);
        
        // Subject Coefficient Management
        Route::apiResource('subject-coefficients', SubjectCoefficientController::class);
        Route::post('/subject-coefficients/bulk', [SubjectCoefficientController::class, 'bulkStore']);
        Route::get('/subjects/{subject}/coefficients', [SubjectCoefficientController::class, 'getSubjectCoefficients']);
        Route::post('/subject-coefficients/get', [SubjectCoefficientController::class, 'getCoefficient']);
        Route::post('/subject-coefficients/getByLevel', [SubjectCoefficientController::class, 'getCoefficientByclassLevel']);
        
        // Class-Subject-Teacher Assignment Management
        Route::apiResource('class-assignments', ClassSubjectTeacherController::class);
        Route::get('/classes/{class}/assignments', [ClassSubjectTeacherController::class, 'getClassAssignments']);
        Route::post('/class-assignments/available-teachers', [ClassSubjectTeacherController::class, 'getAvailableTeachers']);
        Route::post('/class-assignments/coefficient-preview', [ClassSubjectTeacherController::class, 'getCoefficientPreview']);
        
        // Schedule Management
        // Schedule routes - specific routes MUST come before resource routes
        Route::post('/schedules/bulk', [ScheduleController::class, 'bulkStore']);
        Route::post('/schedules/check-conflicts', [ScheduleController::class, 'checkConflicts']);
        Route::get('/schedules/available-slots', [ScheduleController::class, 'getAvailableSlots']);
        Route::get('/schedules/weekly-overview', [ScheduleController::class, 'getWeeklyOverview']);
        Route::get('/schedules/day/{day}', [ScheduleController::class, 'getDaySchedule']);
        Route::get('/schedules/room/{room}', [ScheduleController::class, 'getRoomSchedule']);
        Route::get('/classes/{class}/schedule', [ScheduleController::class, 'getClassSchedule']);
        Route::get('/teachers/{teacher}/schedule', [ScheduleController::class, 'getTeacherSchedule']);
        Route::get('/subjects/{subject}/schedule', [ScheduleController::class, 'getSubjectSchedule']);
        Route::apiResource('schedules', ScheduleController::class);
    });
    
    // Teacher routes
    Route::middleware('role:teacher,admin')->group(function () {
        Route::apiResource('grades', GradeController::class);
        Route::post('/grades/bulk', [GradeController::class, 'bulkStore']);
        Route::apiResource('attendances', AttendanceController::class);
        
        // Grade specific endpoints
        Route::get('/students/{student}/grades', [GradeController::class, 'getStudentGrades']);
        Route::get('/students/{student}/report-card', [GradeController::class, 'getStudentReportCard']);
        Route::get('/classes/{class}/grades', [GradeController::class, 'getClassGrades']);
        Route::get('/classes/{class}/ranking', [GradeController::class, 'getClassRanking']);
        Route::get('/subjects/{subject}/statistics', [GradeController::class, 'getSubjectStatistics']);
        
        // Attendance specific endpoints
        Route::get('/students/{student}/attendances', [AttendanceController::class, 'getAttendanceByStudent']);
        Route::get('/classes/{class}/attendances', [AttendanceController::class, 'getAttendanceByClass']);
        Route::get('/teachers/{teacher}/attendances', [AttendanceController::class, 'getAttendanceByTeacher']);
        Route::get('/subjects/{subject}/attendances', [AttendanceController::class, 'getAttendanceBySubject']);
        
        // Teacher specific endpoints
        Route::get('/teacher/classes', [TeacherController::class, 'myClasses']);
        Route::get('/teacher/students', [TeacherController::class, 'myStudents']);
    });
    
    // Parent routes
    Route::middleware('role:parent,admin')->group(function () {
        Route::get('/parent/students', [StudentController::class, 'myChildren']);
        Route::get('/parent/students/{student}/grades', [GradeController::class, 'getStudentGrades']);
        Route::get('/parent/students/{student}/report-card', [GradeController::class, 'getStudentReportCard']);
        Route::get('/parent/students/{student}/attendances', [AttendanceController::class, 'studentAttendances']);
        Route::get('/parent/students/{student}/payments', [PaymentController::class, 'studentPayments']);
        Route::get('/parent/students/{student}/schedule', [ClassController::class, 'studentSchedule']);
    });
    
    // Shared routes (all authenticated users)
    Route::get('/subjects', [SubjectController::class, 'index']);
    
    // Schedule viewing (accessible by teachers, students, parents, admin)
    Route::get('/my-schedule', [scheduleController::class, 'index']);
});
