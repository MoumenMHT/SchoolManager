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
        if ($grade->student) {
            GradingService::synchronizeAverages(
                $grade->student,
                $grade->semester,
                $grade->academic_year
            );
        }
    }
}
