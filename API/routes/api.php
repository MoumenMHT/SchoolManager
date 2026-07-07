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
use App\Http\Controllers\API\FeeController;
use App\Http\Controllers\API\ContractController;
use App\Http\Controllers\API\BillController;
use App\Http\Controllers\API\SupervisorController;
use App\Http\Controllers\API\LevelController;

// Public routes
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // Admin and Secretariat routes for User Management
    Route::middleware('role:admin,secretariat')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::get('/users/with-profile', [UserController::class, 'withProfile']);
        Route::put('/users/{id}/credentials', [UserController::class, 'updateCredentials']);
        Route::apiResource('users', UserController::class);
    });

    // Admin, Secretariat, Director and Accountant routes
    // Include `accountant` so accountants can access parents (needed when creating contracts)
    Route::middleware('role:admin,secretariat,primary_director,cem_director,lycee_director,accountant')->group(function () {
        // Level management
        Route::apiResource('levels', LevelController::class)->except(['index', 'show']);
        Route::post('/levels/{level}/assign-subjects', [LevelController::class, 'assignSubjects']);

        // Student management - specific routes MUST come before apiResource
        Route::get('/students/without-class', [StudentController::class, 'studentsWithoutClass']);
        Route::get('/students/{student}/history', [StudentController::class, 'getHistory']);
        Route::apiResource('students', StudentController::class);
        
        Route::apiResource('supervisors', SupervisorController::class);
        Route::apiResource('teachers', TeacherController::class);
        Route::apiResource('classes', ClassController::class);
        Route::apiResource('subjects', SubjectController::class);
        Route::get('/parents/without-active-contract', [ParentController::class, 'withoutActiveContract']);
        Route::apiResource('parents', ParentController::class);
        Route::post('/parents/{id}/create-account', [ParentController::class, 'createAccount']);
        Route::get('/parents/{id}/students-with-fees', [ParentController::class, 'studentsWithFees']);
        
        // Dashboard statistics
        Route::get('/dashboard/stats', [DashboardController::class, 'index']);

        // Class management
        Route::put('/classes/remove-from-class/{id}', [ClassController::class, 'removeStudentFromClass']);
        
        // Teacher-Subject Management
        Route::get('/teacher-subjects', [TeacherSubjectController::class, 'index']);
        Route::post('/teacher-subjects', [TeacherSubjectController::class, 'store']);
        Route::post('/teacher-subjects/assign-multiple', [TeacherSubjectController::class, 'assignMultiple']);
        Route::delete('/teacher-subjects/{id}', [TeacherSubjectController::class, 'destroy']);
        Route::post('/teacher-subjects/remove', [TeacherSubjectController::class, 'removeSubjectFromTeacher']);
        Route::get('/teachers/{teacher}/subjects', [TeacherSubjectController::class, 'getTeacherSubjects']);
        Route::get('/subjects/{subject}/teachers', [TeacherSubjectController::class, 'getSubjectTeachers']);
        
        // Subject Coefficient Management - specific routes MUST come before apiResource
        Route::post('/subject-coefficients/bulk', [SubjectCoefficientController::class, 'bulkStore']);
        Route::post('/subject-coefficients/get', [SubjectCoefficientController::class, 'getCoefficient']);
        Route::post('/subject-coefficients/getByLevel', [SubjectCoefficientController::class, 'getCoefficientByclassLevel']);
        Route::get('/subjects/{subject}/coefficients', [SubjectCoefficientController::class, 'getSubjectCoefficients']);
        Route::apiResource('subject-coefficients', SubjectCoefficientController::class);
        
        // Class-Subject-Teacher Assignment Management - specific routes MUST come before apiResource
        Route::post('/class-assignments/available-teachers', [ClassSubjectTeacherController::class, 'getAvailableTeachers']);
        Route::post('/class-assignments/coefficient-preview', [ClassSubjectTeacherController::class, 'getCoefficientPreview']);
        Route::get('/classes/{class}/assignments', [ClassSubjectTeacherController::class, 'getClassAssignments']);
        Route::apiResource('class-assignments', ClassSubjectTeacherController::class);
        
        // Schedule Management
        Route::post('/schedules/generate', [ScheduleController::class, 'generateAll']);
        Route::get('/schedules/export', [ScheduleController::class, 'exportExcel']);
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
    
    // Admin and Accountant routes
    Route::middleware('role:admin,accountant')->group(function () {
        // ============================================
        // PAYMENT SYSTEM ROUTES - PHASE 1-6
        // ============================================
        
        // Fee Management Routes (Phase 1)
        Route::prefix('fees')->group(function () {
            Route::post('/bulk', [FeeController::class, 'bulkStore']);
            Route::post('/copy-to-new-year', [FeeController::class, 'copyToNewYear']);
            Route::get('/available-for-contract', [FeeController::class, 'availableForContract']);
            Route::get('/statistics', [FeeController::class, 'statistics']);
            Route::put('/{id}/toggle-status', [FeeController::class, 'toggleStatus']);
            Route::post('/{fee}/sync-levels', [FeeController::class, 'syncLevels']);
            Route::get('/{fee}/levels', [FeeController::class, 'getLevels']);
        });
        Route::apiResource('fees', FeeController::class);
        
        // Contract Management Routes (Phase 2 & 4 & 5)
        Route::prefix('contracts')->group(function () {
            Route::post('/{id}/add-service', [ContractController::class, 'addService']);
            Route::post('/{id}/withdraw', [ContractController::class, 'withdraw']);
        });
        Route::apiResource('contracts', ContractController::class);
        
        // Payment Processing Routes (Phase 3)
        Route::prefix('payments')->group(function () {
            Route::post('/calculate', [PaymentController::class, 'calculatePayment']);
            Route::get('/financial-reports', [PaymentController::class, 'financialReports']);
            Route::get('/contract-payments/{contractId}', [PaymentController::class, 'contractPayments']);
            Route::get('/contract-statistics/{contractId}', [PaymentController::class, 'contractStatistics']);
            Route::get('/payment-history/{contractId}', [PaymentController::class, 'paymentHistory']);
            Route::get('/parent-dashboard/{parentId}', [PaymentController::class, 'parentDashboard']);
            Route::get('/{id}/receipt', [PaymentController::class, 'receipt']);
            Route::post('/{id}/refund', [PaymentController::class, 'refund']);
        });
        Route::apiResource('payments', PaymentController::class);
        
        // Bill Management Routes
        Route::prefix('bills')->group(function () {
            Route::get('/contract/{contractId}/unpaid', [BillController::class, 'unpaid']);
        });
        Route::apiResource('bills', BillController::class);
    });
    
    // Teacher, Supervisor and Director routes
    Route::middleware('role:teacher,supervisor,admin,primary_director,cem_director,lycee_director')->group(function () {
        // Grade Management - specific routes MUST come before apiResource
        Route::post('/grades/bulk', [GradeController::class, 'bulkStore']);
        Route::get('/grades/analytics/overview', [GradeController::class, 'getAnalyticsOverview']);
        Route::get('/analytics/subject-exercise-averages', [GradeController::class, 'getSubjectExerciseAverages']);
        Route::apiResource('grades', GradeController::class);
        Route::post('/attendances/bulk', [AttendanceController::class, 'bulkStore']);
        Route::get('/attendances/overview', [AttendanceController::class, 'getOverviewByDate']);
        Route::apiResource('attendances', AttendanceController::class);
        
        // Grade specific endpoints
        Route::get('/students/{student}/grades', [GradeController::class, 'getStudentGrades']);
        Route::get('/students/{student}/report-card', [GradeController::class, 'getStudentReportCard']);
        Route::get('/classes/{class}/grades', [GradeController::class, 'getClassGrades']);
        Route::get('/classes/{class}/ranking', [GradeController::class, 'getClassRanking']);
        Route::get('/subjects/{subject}/statistics', [GradeController::class, 'getSubjectStatistics']);

        // Exam management (new arch)
        Route::get('/exams/types', [\App\Http\Controllers\API\ExamController::class, 'getTypes']);
        Route::get('/exams/{exam}/exercise-averages', [GradeController::class, 'getExamExerciseAverages']);
        Route::apiResource('exams', \App\Http\Controllers\API\ExamController::class);
        
        // Attendance specific endpoints
        Route::get('/students/{student}/attendances', [AttendanceController::class, 'getAttendanceByStudent']);
        Route::get('/classes/{class}/attendances', [AttendanceController::class, 'getAttendanceByClass']);
        Route::get('/teachers/{teacher}/attendances', [AttendanceController::class, 'getAttendanceByTeacher']);
        Route::get('/subjects/{subject}/attendances', [AttendanceController::class, 'getAttendanceBySubject']);
        Route::get('/schedules/{schedule}/attendances', [AttendanceController::class, 'getAttendanceBySchedule']);
        
        // Teacher specific endpoints
        Route::get('/teacher/classes', [TeacherController::class, 'myClasses']);
        Route::get('/teacher/students', [TeacherController::class, 'myStudents']);
        Route::get('/teachers/{teacher}/schedule', [ScheduleController::class, 'getTeacherSchedule']);
    });
    
    // Supervisor Portal routes
    Route::middleware('role:supervisor')->group(function () {
        Route::get('/supervisor/classes', [SupervisorController::class, 'myClasses']);
        Route::get('/supervisor/dashboard', [SupervisorController::class, 'dashboard']);
        Route::get('/supervisor/classes/{class}/schedule-today', [SupervisorController::class, 'classScheduleToday']);
    });
    
    // Parent routes
    Route::middleware('role:parent,admin')->group(function () {
        Route::get('/parent/students', [StudentController::class, 'myChildren']);
        Route::get('/parent/students/{student}/grades', [GradeController::class, 'getStudentGrades']);
        Route::get('/parent/students/{student}/report-card', [GradeController::class, 'getStudentReportCard']);
        Route::get('/parent/students/{student}/attendances', [AttendanceController::class, 'studentAttendances']);
        
        // Parent Payment Routes (Phase 6)
        Route::get('/parent/dashboard', [PaymentController::class, 'parentDashboard']);
        Route::get('/parent/contracts', [ContractController::class, 'index']);
        Route::get('/parent/contracts/{id}', [ContractController::class, 'show']);
        Route::get('/parent/payments', [PaymentController::class, 'index']);
        Route::get('/parent/bills', [BillController::class, 'index']);
        
        Route::get('/parent/students/{student}/payments', [PaymentController::class, 'studentPayments']);
        Route::get('/parent/students/{student}/schedule', [ClassController::class, 'studentSchedule']);
    });
    
    // Shared routes (all authenticated users)
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/levels', [LevelController::class, 'index']);
    Route::get('/levels/{level}/subjects', [LevelController::class, 'subjects']);
    
    // Schedule viewing (accessible by teachers, students, parents, admin)
    Route::get('/my-schedule', [ScheduleController::class, 'index']);
});
