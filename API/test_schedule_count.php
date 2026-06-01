<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$teacherId = 1; // Try with Teacher ID 1
$count = \App\Models\Schedule::whereHas('assignment', function($q) use ($teacherId) {
    $q->where('teacher_id', $teacherId);
})->count();
echo "Teacher ID 1 schedules: $count\n";

$teacherId = 2; // Try with Teacher ID 2
$count = \App\Models\Schedule::whereHas('assignment', function($q) use ($teacherId) {
    $q->where('teacher_id', $teacherId);
})->count();
echo "Teacher ID 2 schedules: $count\n";

$teacherId = 3; // Try with Teacher ID 3
$count = \App\Models\Schedule::whereHas('assignment', function($q) use ($teacherId) {
    $q->where('teacher_id', $teacherId);
})->count();
echo "Teacher ID 3 schedules: $count\n";

$teacherId = 4; // Try with Teacher ID 4
$count = \App\Models\Schedule::whereHas('assignment', function($q) use ($teacherId) {
    $q->where('teacher_id', $teacherId);
})->count();
echo "Teacher ID 4 schedules: $count\n";
