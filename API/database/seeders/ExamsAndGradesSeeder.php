<?php

namespace Database\Seeders;

use App\Models\ClassSubjectTeacher;
use App\Models\Exam;
use App\Models\ExamExercise;
use App\Models\Grade;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamsAndGradesSeeder extends Seeder
{
    /**
     * Seeds exams, exercises, and grades for all 3 trimesters.
     * Optimised: bulk inserts, pre-loaded lookups, no N+1 queries.
     */
    public function run(): void
    {
        $this->command->info('🎓 Seeding exams, exercises, and grades (3 trimesters)…');

        $classes = SchoolClass::with(['students'])->get();
        if ($classes->isEmpty()) {
            $this->command->warn('No classes found. Run the base seeder first.');
            return;
        }

        $academicYear = '2025-2026';
        $semesters    = ['Trimester 1', 'Trimester 2', 'Trimester 3'];
        $examTypes    = [
            'devoir_1'    => ['max' => 20, 'exercises' => [['n' => 'تمرين 1', 'm' => 8], ['n' => 'تمرين 2', 'm' => 8], ['n' => 'تمرين 3', 'm' => 4]]],
            'devoir_2'    => ['max' => 20, 'exercises' => [['n' => 'تمرين 1', 'm' => 8], ['n' => 'تمرين 2', 'm' => 8], ['n' => 'تمرين 3', 'm' => 4]]],
            'composition' => ['max' => 20, 'exercises' => [['n' => 'الجزء الأول', 'm' => 10], ['n' => 'الجزء الثاني', 'm' => 10]]],
        ];

        // ── 1 query: all assignments grouped by class ─────────────────────
        $assignments = ClassSubjectTeacher::where('academic_year', $academicYear)
            ->get()
            ->groupBy('class_id');

        // ── 1 query: existing exams → hash map to skip firstOrCreate SELECT ─
        $existingExams = Exam::where('academic_year', $academicYear)
            ->get()
            ->keyBy(fn($e) => "{$e->subject_id}|{$e->teacher_id}|{$e->exam_type}|{$e->semester}");

        // ── 1 query: existing class_exam pivot keys ────────────────────────
        $existingClassExam = DB::table('class_exam')
            ->get(['exam_id', 'class_id'])
            ->mapWithKeys(fn($r) => ["{$r->exam_id}|{$r->class_id}" => true])
            ->all();

        // ── 1 query: existing grade keys (idempotency) ────────────────────
        $existingGrades = Grade::all(['student_id', 'exam_id'])
            ->mapWithKeys(fn($g) => ["{$g->student_id}|{$g->exam_id}" => true])
            ->all();

        // Exercise cache: exam_id → collection
        $exerciseCache = [];

        $totalExams  = 0;
        $totalGrades = 0;

        foreach ($classes as $class) {
            $classAssignments = $assignments->get($class->id, []);
            if (empty($classAssignments)) continue;

            foreach ($classAssignments as $assignment) {
                foreach ($semesters as $semester) {
                    foreach ($examTypes as $type => $def) {
                        $examKey = "{$assignment->subject_id}|{$assignment->teacher_id}|{$type}|{$semester}";

                        // ── Get or create exam using in-memory map ─────────
                        if (isset($existingExams[$examKey])) {
                            $exam   = $existingExams[$examKey];
                            $wasNew = false;
                        } else {
                            $exam = Exam::create([
                                'subject_id'    => $assignment->subject_id,
                                'teacher_id'    => $assignment->teacher_id,
                                'exam_type'     => $type,
                                'semester'      => $semester,
                                'academic_year' => $academicYear,
                                'max_grade'     => $def['max'],
                            ]);
                            $existingExams[$examKey] = $exam;
                            $wasNew = true;
                            $totalExams++;
                        }

                        // ── class_exam pivot (skip if already exists) ──────
                        $pivotKey = "{$exam->id}|{$class->id}";
                        if (!isset($existingClassExam[$pivotKey])) {
                            DB::table('class_exam')->insertOrIgnore(['exam_id' => $exam->id, 'class_id' => $class->id]);
                            $existingClassExam[$pivotKey] = true;
                        }

                        // ── Bulk-create exercises once per new exam ────────
                        if ($wasNew) {
                            $batch = [];
                            foreach ($def['exercises'] as $ex) {
                                $batch[] = ['exam_id' => $exam->id, 'level_name' => $ex['n'], 'max_note' => $ex['m'], 'created_at' => now(), 'updated_at' => now()];
                            }
                            ExamExercise::insert($batch);
                        }

                        // ── Fetch exercises once and cache per exam ────────
                        if (!isset($exerciseCache[$exam->id])) {
                            $exerciseCache[$exam->id] = ExamExercise::where('exam_id', $exam->id)->get();
                        }
                        $examExercises = $exerciseCache[$exam->id];

                        $exerciseGradeData = [];
                        $now = now();

                        foreach ($class->students as $student) {
                            // ── Skip already-graded students ───────────────
                            $gradeKey = "{$student->id}|{$exam->id}";
                            if (isset($existingGrades[$gradeKey])) continue;

                            $totalScore       = 0;
                            $studentExercises = [];
                            foreach ($examExercises as $ex) {
                                $score = min(round($ex->max_note * $this->gaussianRand(0.55, 1.0), 2), (float) $ex->max_note);
                                $totalScore += $score;
                                $studentExercises[$ex->id] = $score;
                            }

                            $gradeId = DB::table('grades')->insertGetId([
                                'student_id' => $student->id,
                                'exam_id'    => $exam->id,
                                'grade'      => min(round($totalScore, 2), (float) $exam->max_grade),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                            $existingGrades[$gradeKey] = true;
                            $totalGrades++;

                            foreach ($studentExercises as $exId => $score) {
                                $exerciseGradeData[] = [
                                    'grade_id'         => $gradeId,
                                    'exam_exercise_id' => $exId,
                                    'note'             => $score,
                                    'created_at'       => $now,
                                    'updated_at'       => $now,
                                ];
                            }
                        }

                        // ── Bulk-insert all exercise_grades for this exam ──
                        if (!empty($exerciseGradeData)) {
                            DB::table('exercise_grades')->insert($exerciseGradeData);
                        }

                    } // foreach examType
                } // foreach semester
            } // foreach assignment
        } // foreach class
        $this->command->info("✅ Seeded {$totalExams} exams and {$totalGrades} grade records.");
    }

    private function gaussianRand(float $min, float $max): float
    {
        return $min + (($mt = (mt_rand(0, 1000) / 1000 + mt_rand(0, 1000) / 1000) / 2)) * ($max - $min);
    }
}
