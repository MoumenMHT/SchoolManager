<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Payment;
use App\Models\Contract;
use App\Models\Attendance;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function index(Request $request)
    {
        $academicYear = $request->get('academic_year', date('Y') . '-' . (date('Y') + 1));
        
        $user = auth()->user();
        $isDirector = $user && method_exists($user, 'isDirector') && $user->isDirector();
        $directorCycle = $isDirector ? $user->directorCycle() : null;

        // Total students
        $studentQuery = Student::where('is_active', true);
        if ($isDirector) {
            $studentQuery->whereHas('class.levelProfile', function ($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }
        $totalStudents = $studentQuery->count();
        
        // Total teachers
        $teacherQuery = Teacher::query();
        if ($isDirector) {
            $teacherQuery->whereHas('classes.levelProfile', function ($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }
        $totalTeachers = $teacherQuery->count();
        
        // Total classes
        $classQuery = SchoolClass::where('is_active', true)
            ->where('academic_year', $academicYear);
        if ($isDirector) {
            $classQuery->whereHas('levelProfile', function ($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }
        $totalClasses = $classQuery->count();

        // Revenue statistics
        if ($isDirector) {
            $totalRevenue = 0;
            $pendingPayments = 0;
            $latePayments = 0;
            $paymentRate = 0;
            $recentPayments = [];
        } else {
            $totalRevenue = Payment::whereHas('contract', function ($q) use ($academicYear) {
                    $q->where('academic_year', $academicYear);
                })
                ->where('status', 'completed')
                ->sum('amount');
                
            $pendingPayments = Payment::whereHas('contract', function ($q) use ($academicYear) {
                    $q->where('academic_year', $academicYear);
                })
                ->where('status', 'pending')
                ->sum('amount');
                
            $latePayments = Payment::whereHas('contract', function ($q) use ($academicYear) {
                    $q->where('academic_year', $academicYear);
                })
                ->where('status', 'late')
                ->sum('amount');

            $totalExpected = Contract::where('academic_year', $academicYear)
                ->sum(DB::raw('total_fees - discount_value'));
            $paymentRate = $totalExpected > 0 ? ($totalRevenue / $totalExpected) * 100 : 0;
            
            $recentPayments = Payment::with(['contract.parent'])
                ->where('status', 'completed')
                ->latest('paid_date')
                ->limit(10)
                ->get();
        }

        // Attendance statistics (last 30 days)
        $startDate = now()->subDays(30);
        $attendanceQuery = Attendance::where('date', '>=', $startDate);
        if ($isDirector) {
             $attendanceQuery->whereHas('student.class.levelProfile', function ($q) use ($directorCycle) {
                 $q->where('cycle', $directorCycle);
             });
        }
        $attendanceStats = $attendanceQuery
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $totalAttendance = $attendanceStats->sum();
        $attendanceRate = $totalAttendance > 0 
            ? (($attendanceStats->get('present', 0) / $totalAttendance) * 100) 
            : 0;

        // Average grades (current academic year)
        $gradeQuery = Grade::where('academic_year', $academicYear);
        if ($isDirector) {
             $gradeQuery->whereHas('student.class.levelProfile', function ($q) use ($directorCycle) {
                 $q->where('cycle', $directorCycle);
             });
        }
        $averageGrade = $gradeQuery->avg('grade');

        // Students by class
        $studentsByClassQuery = SchoolClass::where('is_active', true)
            ->where('academic_year', $academicYear);
        if ($isDirector) {
            $studentsByClassQuery->whereHas('levelProfile', function ($q) use ($directorCycle) {
                $q->where('cycle', $directorCycle);
            });
        }
        $studentsByClass = $studentsByClassQuery
            ->withCount('students')
            ->get()
            ->map(function ($class) {
                return [
                    'class_name' => $class->name,
                    'student_count' => $class->students_count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_students' => $totalStudents,
                    'total_teachers' => $totalTeachers,
                    'total_classes' => $totalClasses,
                ],
                'financial' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'pending_payments' => round($pendingPayments, 2),
                    'late_payments' => round($latePayments, 2),
                    'payment_rate' => round($paymentRate, 2),
                ],
                'academic' => [
                    'average_grade' => round($averageGrade ?? 0, 2),
                    'attendance_rate' => round($attendanceRate, 2),
                ],
                'attendance_breakdown' => $attendanceStats,
                'students_by_class' => $studentsByClass,
                'recent_payments' => $recentPayments,
            ]
        ]);
    }
}