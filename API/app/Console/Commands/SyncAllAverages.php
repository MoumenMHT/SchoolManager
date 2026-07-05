<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Grade;
use App\Models\Student;
use App\Services\GradingService;

class SyncAllAverages extends Command
{
    protected $signature = 'averages:sync';
    protected $description = 'Backfill the student_averages table from all existing grade records.';

    public function handle(): void
    {
        $this->info('Fetching all unique student/trimester/year combinations...');

        $combos = Grade::join('exams', 'grades.exam_id', '=', 'exams.id')
            ->selectRaw('grades.student_id, exams.semester, exams.academic_year')
            ->distinct()
            ->get();

        $this->info("Found {$combos->count()} combinations to process.");

        $bar = $this->output->createProgressBar($combos->count());
        $bar->start();

        foreach ($combos as $combo) {
            $student = Student::with('class.levelProfile')->find($combo->student_id);
            if ($student && $student->class && $student->class->levelProfile) {
                GradingService::synchronizeAverages($student, $combo->semester, $combo->academic_year);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done! student_averages table is now in sync.');
    }
}
