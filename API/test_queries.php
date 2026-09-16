<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Stancl\Tenancy\Facades\Tenancy::initialize('school1');
$user = App\Models\User::find(1);
Auth::guard('web')->login($user);
$academicYearId = \App\Models\AcademicYear::where('is_current', true)->value('id');

try {
    echo "1. Student Query\n";
    $studentQuery = App\Models\Student::where('is_active', true);
    $totalStudents = $studentQuery->count();
} catch (\Exception $e) {
    echo "Error 1: " . $e->getMessage() . "\n";
}

try {
    echo "2. Teacher Query\n";
    $teacherQuery = App\Models\Teacher::where('is_active', true);
    $totalTeachers = $teacherQuery->count();
} catch (\Exception $e) {
    echo "Error 2: " . $e->getMessage() . "\n";
}

try {
    echo "3. Class Query\n";
    $classQuery = App\Models\SchoolClass::where('is_active', true)
        ->where('academic_year_id', $academicYearId);
    $totalClasses = $classQuery->count();
} catch (\Exception $e) {
    echo "Error 3: " . $e->getMessage() . "\n";
}
