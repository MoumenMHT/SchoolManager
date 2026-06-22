<?php

namespace Database\Seeders;

use App\Models\ClassSubjectTeacher;
use App\Models\Exam;
use App\Models\ExamExercise;
use App\Models\ExerciseGrade;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamsAndGradesSeeder extends Seeder
{
    /**
     * Seeds exams, exercises, and grades for all 3 trimesters
     * using the correct teacher-per-class-per-subject assignment.
     */
    public function run(): void
    {
        $this->command->info('🎓 Seeding exams, exercises, and grades (3 trimesters)…');

        $classes = SchoolClass::with(['students', 'levelProfile'])->get();

        if ($classes->isEmpty()) {
            $this->command->warn('No classes found. Run the base seeder first.');
            return;
        }

        $academicYear = '2025-2026';

        // Exam types per trimester: 2 devoirs + 1 composition each
        $semesters = ['Trimester 1', 'Trimester 2', 'Trimester 3'];

        // Exercise structure per exam type
        $exerciseTemplates = [
            'devoir_1'    => [
                ['level_name' => 'تمرين 1', 'max_note' => 8],
                ['level_name' => 'تمرين 2', 'max_note' => 8],
                ['level_name' => 'تمرين 3', 'max_note' => 4],
            ],
            'devoir_2'    => [
                ['level_name' => 'تمرين 1', 'max_note' => 8],
                ['level_name' => 'تمرين 2', 'max_note' => 8],
                ['level_name' => 'تمرين 3', 'max_note' => 4],
            ],
            'composition' => [
                ['level_name' => 'الجزء الأول',  'max_note' => 10],
                ['level_name' => 'الجزء الثاني', 'max_note' => 10],
            ],
        ];

        $examTypes = [
            ['type' => 'devoir_1',    'max' => 20],
            ['type' => 'devoir_2',    'max' => 20],
            ['type' => 'composition', 'max' => 20],
        ];

        $totalExams    = 0;
        $totalExercise = 0;
        $totalGrades   = 0;

        foreach ($classes as $class) {
            $students = $class->students;
            if ($students->isEmpty()) {
                continue;
            }

            // Load all class-subject-teacher assignments for this class
            $assignments = ClassSubjectTeacher::where('class_id', $class->id)
                ->where('academic_year', $academicYear)
                ->get();

            if ($assignments->isEmpty()) {
                // Fallback: skip if no assignments
                continue;
            }

            foreach ($assignments as $assignment) {
                $subject   = Subject::find($assignment->subject_id);
                $teacherId = $assignment->teacher_id;

                if (!$subject) continue;

                foreach ($semesters as $semester) {
                    foreach ($examTypes as $examDef) {
                        // Idempotency check: find existing exam for this
                        // teacher × subject × type × semester
                        $exam = Exam::firstOrCreate(
                            [
                                'subject_id'    => $subject->id,
                                'teacher_id'    => $teacherId,
                                'exam_type'     => $examDef['type'],
                                'semester'      => $semester,
                                'academic_year' => $academicYear,
                            ],
                            ['max_grade' => $examDef['max']]
                        );

                        $wasRecentlyCreated = $exam->wasRecentlyCreated;
                        if ($wasRecentlyCreated) {
                            $totalExams++;
                        }

                        // Link exam to this class (pivot)
                        DB::table('class_exam')->insertOrIgnore([
                            'exam_id'  => $exam->id,
                            'class_id' => $class->id,
                        ]);

                        // Create exercises if newly created exam
                        if ($wasRecentlyCreated) {
                            foreach ($exerciseTemplates[$examDef['type']] as $exDef) {
                                ExamExercise::create([
                                    'exam_id'    => $exam->id,
                                    'level_name' => $exDef['level_name'],
                                    'max_note'   => $exDef['max_note'],
                                ]);
                                $totalExercise++;
                            }
                        }

                        // Fetch exercises
                        $exercises = ExamExercise::where('exam_id', $exam->id)->get();

                        // Grade each student
                        foreach ($students as $student) {
                            $alreadyGraded = Grade::where('student_id', $student->id)
                                ->where('exam_id', $exam->id)
                                ->exists();
                            if ($alreadyGraded) continue;

                            // Generate realistic scores (slightly bell-curved)
                            $exerciseScores = [];
                            $totalScore     = 0;

                            foreach ($exercises as $exercise) {
                                // Give each student a performance profile (0.5–1.0)
                                $performanceRatio = $this->gaussianRand(0.55, 1.0);
                                $score = round($exercise->max_note * $performanceRatio, 2);
                                $score = min(max($score, 0), (float) $exercise->max_note);
                                $exerciseScores[$exercise->id] = $score;
                                $totalScore += $score;
                            }

                            $totalScore = min(round($totalScore, 2), (float) $exam->max_grade);

                            $grade = Grade::create([
                                'student_id' => $student->id,
                                'exam_id'    => $exam->id,
                                'grade'      => $totalScore,
                                'comment'    => null,
                            ]);
                            $totalGrades++;

                            foreach ($exercises as $exercise) {
                                ExerciseGrade::create([
                                    'grade_id'         => $grade->id,
                                    'exam_exercise_id' => $exercise->id,
                                    'note'             => $exerciseScores[$exercise->id],
                                ]);
                            }
                        }
                    } // foreach examType
                } // foreach semester
            } // foreach assignment
        } // foreach class

        $this->command->info("✅ Seeded {$totalExams} exams, {$totalExercise} exercises, {$totalGrades} grade records.");
    }

    /**
     * Returns a random float between $min and $max, slightly weighted toward
     * the middle (simple average of two uniform random numbers).
     */
    private function gaussianRand(float $min, float $max): float
    {
        $r1 = mt_rand(0, 1000) / 1000;
        $r2 = mt_rand(0, 1000) / 1000;
        $avg = ($r1 + $r2) / 2; // central-limit approximation
        return $min + $avg * ($max - $min);
    }
}
