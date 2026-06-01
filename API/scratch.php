<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$studentId = 1;
$request = Illuminate\Http\Request::create("/api/parent/students/$studentId/grades", 'GET');
// Mocking user: we don't really have the token so let's bypass the middleware or just run the exact logic
$student = App\Models\Student::find($studentId);
$grades = App\Models\Grade::with(['exam.subject', 'exam.teacher', 'exerciseGrades.exercise'])->where('student_id', $studentId)->get();
echo json_encode([
    'success' => true,
    'data' => [
        'student' => $student,
        'grades'  => $grades,
    ]
], JSON_PRETTY_PRINT);
