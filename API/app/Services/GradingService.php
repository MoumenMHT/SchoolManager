<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GradingService
{
    /**
     * Calculate the subject average for a student given their grades in that subject.
     * Extracts evaluation_continue, devoir, and composition to apply Algerian formula.
     * 
     * @param Collection $grades
     * @param string $cycle ('primaire', 'cem', 'secondaire')
     * @return float
     */
    public static function calculateSubjectAverage(Collection $grades, string $cycle): float
    {
        if ($grades->isEmpty()) {
            return 0.0;
        }

        // Primary: simple average of compositions natively out of 10
        if (strtolower($cycle) === 'primaire') {
            $compositions = $grades->where('exam_type', 'composition');
            if ($compositions->isEmpty()) {
                // fallback to simple average if no exact composition exists
                return round($grades->avg('grade') ?? 0, 2);
            }
            return round($compositions->avg('grade') ?? 0, 2);
        }

        // CEM and Lycee (out of 20)
        // Ensure grades are out of 20 (normalize if needed, though they should be 20)
        $normalize = fn($g) => $g->max_grade > 0 ? ($g->grade / $g->max_grade) * 20 : 0;

        $ccGrades = $grades->where('exam_type', 'evaluation_continue');
        $devoirGrades = $grades->where('exam_type', 'devoir');
        $compositionGrades = $grades->where('exam_type', 'composition');

        $ccAvg = $ccGrades->avg($normalize) ?? 0;
        $devoirAvg = $devoirGrades->avg($normalize) ?? 0;
        $compositionAvg = $compositionGrades->avg($normalize) ?? 0;

        // If a student misses a specific component, we try to be resilient
        if ($compositionGrades->isEmpty()) {
            // No term exam? Fallback to whatever they have
            return round(($ccAvg + $devoirAvg) / max(1, ($ccGrades->isEmpty() ? 0 : 1) + ($devoirGrades->isEmpty() ? 0 : 1)), 2);
        }

        if ($ccGrades->isEmpty() && $devoirGrades->isEmpty()) {
            // Only composition?
            return round($compositionAvg, 2);
        }

        // Standard Algerian CEM/Lycee Formula: (((CC + Devoir)/2) + (Composition * 2)) / 3
        $continuousAvg = ($ccAvg + $devoirAvg) / max(1, ($ccGrades->isEmpty() ? 0 : 1) + ($devoirGrades->isEmpty() ? 0 : 1));
        
        $subjectAverage = ($continuousAvg + ($compositionAvg * 2)) / 3;

        return round($subjectAverage, 2);
    }

    /**
     * Synchronize all computed averages for a student in a specific trimester into the database.
     * This handles both individual Subject averages and the Overall trimester average.
     */
    public static function synchronizeAverages(\App\Models\Student $student, string $trimester, string $academicYear): void
    {
        $grades = \App\Models\Grade::with('subject')
            ->where('student_id', $student->id)
            ->where('semester', $trimester) // historically named 'semester' in Grade model still
            ->where('academic_year', $academicYear)
            ->get();

        $student->loadMissing('class.levelProfile');
        $cycle = $student->class->levelProfile->cycle ?? 'cem';
        $levelId = $student->class->level_id;
        $classId = $student->class_id;

        // Get subjects assigned to this specific class
        $classSubjects = \App\Models\ClassSubjectTeacher::where('class_id', $classId)
            ->pluck('subject_id')
            ->toArray();

        $gradesBySubject = $grades->groupBy('subject_id');
        
        $totalSum = 0;
        $totalCoef = 0;

        // Ensure we calculate for ALL subjects assigned to the class, even if no grades exist
        foreach ($classSubjects as $subjectId) {
            $subjectGrades = $gradesBySubject->get($subjectId, collect());
            $average = self::calculateSubjectAverage($subjectGrades, $cycle);
            $coefficient = \App\Models\LevelSubject::getCoefficient($subjectId, $levelId) ?? 1;

            // Upsert Subject Average
            \App\Models\StudentAverage::updateOrCreate([
                'student_id' => $student->id,
                'subject_id' => $subjectId,
                'record_type' => 'subject',
                'trimester' => $trimester,
                'academic_year' => $academicYear,
            ], [
                'class_id' => $classId,
                'average' => $average,
            ]);

            $totalSum += ($average * $coefficient);
            $totalCoef += $coefficient;
        }

        // Delete subject averages for subjects not explicitly assigned to the class
        \App\Models\StudentAverage::where('student_id', $student->id)
            ->where('record_type', 'subject')
            ->where('trimester', $trimester)
            ->where('academic_year', $academicYear)
            ->whereNotIn('subject_id', $classSubjects)
            ->delete();

        // Upsert Overall Average
        $overallAverage = $totalCoef > 0 ? round($totalSum / $totalCoef, 2) : 0;
        
        if (!empty($classSubjects)) {
            \App\Models\StudentAverage::updateOrCreate([
                'student_id' => $student->id,
                'subject_id' => null,
                'record_type' => 'overall',
                'trimester' => $trimester,
                'academic_year' => $academicYear,
            ], [
                'class_id' => $classId,
                'average' => $overallAverage,
            ]);
        } else {
            // Delete if no subjects remain
            \App\Models\StudentAverage::where('student_id', $student->id)
                ->where('trimester', $trimester)
                ->where('academic_year', $academicYear)
                ->delete();
        }
    }
}
