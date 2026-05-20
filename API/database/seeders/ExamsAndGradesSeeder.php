<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamExercise;
use App\Models\ExerciseGrade;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamsAndGradesSeeder extends Seeder
{
    /**
     * Seeds exams, exercises, and grades for already-existing students.
     * Safe to re-run: skips classes that already have exam data.
     */
    public function run(): void
    {
        $this->command->info('🎓 Seeding exams, exercises, and grades...');

        $classes   = SchoolClass::with('students')->get();
        $subjects  = Subject::all()->keyBy('id');
        $teachers  = Teacher::all();

        if ($classes->isEmpty() || $teachers->isEmpty() || $subjects->isEmpty()) {
            $this->command->warn('No classes, teachers, or subjects found. Run the base seeder first.');
            return;
        }

        // ── Config ────────────────────────────────────────────────────────────
        $academicYear = '2025-2026';
        $semesters    = ['Trimester 1'];
        // Exercise templates — devoir_1 and devoir_2 share the same structure
        $exerciseTemplates = [
            'devoir_1'    => [
                ['level_name' => 'تمرين 1',  'max_note' => 8],
                ['level_name' => 'تمرين 2',  'max_note' => 8],
                ['level_name' => 'تمرين 3',  'max_note' => 4],
            ],
            'devoir_2'    => [
                ['level_name' => 'تمرين 1',  'max_note' => 8],
                ['level_name' => 'تمرين 2',  'max_note' => 8],
                ['level_name' => 'تمرين 3',  'max_note' => 4],
            ],
            'composition' => [
                ['level_name' => 'الجزء الأول',  'max_note' => 10],
                ['level_name' => 'الجزء الثاني', 'max_note' => 10],
            ],
        ];

        $totalGrades   = 0;
        $totalExams    = 0;
        $totalExercise = 0;

        foreach ($classes as $class) {
            $students = $class->students;
            if ($students->isEmpty()) {
                continue;
            }

            // Pick subjects relevant to this class's level
            $classSubjects = $subjects->values()->take(6); // use first 6 subjects per class

            // Assign a teacher per subject (cycle through available teachers)
            $teacherPool = $teachers->values();
            $subjectTeachers = [];
            foreach ($classSubjects as $idx => $subject) {
                $subjectTeachers[$subject->id] = $teacherPool[$idx % $teacherPool->count()];
            }

            foreach ($classSubjects as $subject) {
                $teacher = $subjectTeachers[$subject->id];

                foreach ($semesters as $semIdx => $semester) {
                    // Determine devoirs count (2 per semester for devoir, 1 composition)
                    $examsForSemester = [
                        ['type' => 'devoir_1',    'max' => 20],
                        ['type' => 'devoir_2',    'max' => 20],
                        ['type' => 'composition', 'max' => 20],
                    ];

                    foreach ($examsForSemester as $examDef) {
                        // Avoid creating duplicate exams (idempotency check)
                        $existingExam = Exam::where([
                            'subject_id'    => $subject->id,
                            'teacher_id'    => $teacher->id,
                            'exam_type'     => $examDef['type'],
                            'semester'      => $semester,
                            'academic_year' => $academicYear,
                        ])->first();

                        if ($existingExam) {
                            // Check if this class already has grades → skip
                            $hasGrades = Grade::whereIn('student_id', $students->pluck('id'))
                                ->where('exam_id', $existingExam->id)
                                ->exists();
                            if ($hasGrades) {
                                continue;
                            }
                            $exam = $existingExam;
                        } else {
                            /** @var Exam $exam */
                            $exam = Exam::create([
                                'subject_id'    => $subject->id,
                                'teacher_id'    => $teacher->id,
                                'exam_type'     => $examDef['type'],
                                'semester'      => $semester,
                                'academic_year' => $academicYear,
                                'max_grade'     => $examDef['max'],
                            ]);

                            $totalExams++;

                            // Link exam to this class
                            DB::table('class_exam')->insertOrIgnore([
                                'exam_id'  => $exam->id,
                                'class_id' => $class->id,
                            ]);

                            // Create exercises for the exam
                            $template = $exerciseTemplates[$examDef['type']];
                            $exercises = [];
                            foreach ($template as $exDef) {
                                $exercise = ExamExercise::create([
                                    'exam_id'    => $exam->id,
                                    'level_name' => $exDef['level_name'],
                                    'max_note'   => $exDef['max_note'],
                                ]);
                                $exercises[] = $exercise;
                                $totalExercise++;
                            }
                        }

                        // Fetch exercises (whether newly created or pre-existing)
                        $exercises = ExamExercise::where('exam_id', $exam->id)->get();

                        // Create grade rows per student
                        foreach ($students as $student) {
                            // Skip if grade already exists
                            $alreadyGraded = Grade::where('student_id', $student->id)
                                ->where('exam_id', $exam->id)
                                ->exists();
                            if ($alreadyGraded) continue;

                            // Compute a realistic total from exercise scores
                            $exerciseScores = [];
                            $totalScore     = 0;
                            foreach ($exercises as $exercise) {
                                // Gaussian-like random between 40% and 100% of max_note
                                $min = $exercise->max_note * 0.4;
                                $max = $exercise->max_note;
                                $score = round($min + (($max - $min) * (mt_rand(0, 100) / 100)), 2);
                                $score = min($score, (float) $exercise->max_note);
                                $exerciseScores[$exercise->id] = $score;
                                $totalScore += $score;
                            }

                            // Cap to exam max_grade
                            $totalScore = min(round($totalScore, 2), (float) $exam->max_grade);

                            /** @var Grade $grade */
                            $grade = Grade::create([
                                'student_id' => $student->id,
                                'exam_id'    => $exam->id,
                                'grade'      => $totalScore,
                                'comment'    => null,
                            ]);

                            $totalGrades++;

                            // Create per-exercise grade rows
                            foreach ($exercises as $exercise) {
                                ExerciseGrade::create([
                                    'grade_id'         => $grade->id,
                                    'exam_exercise_id' => $exercise->id,
                                    'note'             => $exerciseScores[$exercise->id],
                                ]);
                            }
                        }
                    } // foreach examDef
                } // foreach semester
            } // foreach subject
        } // foreach class

        $this->command->info("✅ Seeded {$totalExams} exams, {$totalExercise} exercises, {$totalGrades} grade records.");
    }
}
