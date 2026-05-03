<?php

namespace App\Observers;

use App\Models\Grade;
use App\Services\GradingService;

class GradeObserver
{
    /**
     * Handle the Grade "saved" event.
     * Fires on create and update.
     */
    public function saved(Grade $grade): void
    {
        $this->sync($grade);
    }

    /**
     * Handle the Grade "deleted" event.
     */
    public function deleted(Grade $grade): void
    {
        $this->sync($grade);
    }

    /**
     * Centralized synchronization method.
     */
    private function sync(Grade $grade): void
    {
        // We need the student and the exam to sync averages
        // Load them if not already loaded
        $student = $grade->student;
        $exam = $grade->exam;

        if ($student && $exam) {
            GradingService::synchronizeAverages(
                $student,
                $exam->semester,
                $exam->academic_year
            );
        }
    }
}
