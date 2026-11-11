<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$studentId = 131; // STU1130
$semester = 'Trimester 1';
$ayVal = '2026-2027';

$ayId = \App\Models\AcademicYear::where('name', $ayVal)->value('id');

$averagesQuery = \App\Models\StudentAverage::with('subject')
    ->where('student_id', $studentId)
    ->where('trimester', $semester);
if ($ayId) {
    $averagesQuery->where('academic_year_id', $ayId);
}
$averages = $averagesQuery->get();
$subjectAverages = $averages->where('record_type', 'subject')->values();
$overallAverageRow = $averages->where('record_type', 'overall')->first();

echo "Overall average row exists: " . ($overallAverageRow ? "Yes" : "No") . "\n";
if ($overallAverageRow) echo "Value: " . $overallAverageRow->average . "\n";
echo "Subject averages count: " . $subjectAverages->count() . "\n";
