<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$student = App\Models\Student::where('first_name', 'like', '%بلال%')->orWhere('last_name', 'like', '%مصطفى%')->first();
if (!$student) {
    echo "Student not found\n";
    exit;
}
echo "Student ID: {$student->id}\n";
$grades = App\Models\Grade::with('exam')->where('student_id', $student->id)->get();
echo "--- Grades ---\n";
foreach($grades as $g) {
    echo "Grade ID: {$g->id} | Grade: {$g->grade}/" . ($g->exam ? $g->exam->max_grade : 'N/A') . " (" . ($g->exam ? $g->exam->exam_type : 'N/A') . ") Subj: " . ($g->exam ? $g->exam->subject_id : 'N/A') . "\n";
}
echo "--- Averages ---\n";
$averages = App\Models\StudentAverage::where('student_id', $student->id)->get();
foreach($averages as $a) {
    echo "Average ID: {$a->id} | Avg: {$a->average} ({$a->record_type}) Subj: {$a->subject_id} Tri: {$a->trimester} AY_ID: {$a->academic_year_id}\n";
}
