<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ClassSubjectTeacher;
use App\Models\LevelSubject;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AttendanceSeeder – June 2026
 *
 * Steps:
 *  1. Read existing students, classes, teachers and subjects from the database.
 *  2. Create ClassSubjectTeacher (CST) assignments if none exist.
 *  3. Create a weekly schedule (Sunday–Thursday) for each CST assignment.
 *  4. Generate realistic attendance records for every school day in June 2026,
 *     one attendance row per (student x schedule session) for that day.
 *
 * School days: Sunday–Thursday (Algeria academic calendar).
 */
class AttendanceSeeder extends Seeder
{
    // Probability weights for attendance statuses
    private const STATUS_WEIGHTS = [
        'present' => 80,
        'absent'  => 10,
        'late'    => 7,
        'excused' => 3,
    ];

    private const ABSENCE_REASONS = [
        'مرض',
        'ظروف عائلية',
        'حادث',
        'سفر',
        'موعد طبي',
        'أسباب شخصية',
    ];

    private const SCHOOL_DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

    // Time slots: [start, end]
    private const TIME_SLOTS = [
        ['08:00', '08:55'],
        ['08:55', '09:50'],
        ['10:05', '11:00'],
        ['11:00', '11:55'],
        ['13:00', '13:55'],
        ['13:55', '14:50'],
    ];

    public function run(): void
    {
        $this->command->info('Seeding Attendance for June 2026...');

        $classes  = SchoolClass::where('is_active', true)->get();
        $students = Student::where('is_active', true)->get();

        if ($classes->isEmpty()) {
            $this->command->error('No classes found. Run the DatabaseSeeder first.');
            return;
        }

        if ($students->isEmpty()) {
            $this->command->error('No students found. Run the DatabaseSeeder first.');
            return;
        }

        $this->command->info("Found {$classes->count()} classes and {$students->count()} students.");

        // Step 2: Ensure CST assignments exist
        if (ClassSubjectTeacher::count() === 0) {
            $this->command->info('No CST records found, creating assignments...');
            $this->createCSTAssignments($classes);
        } else {
            $this->command->info('CST assignments already exist (' . ClassSubjectTeacher::count() . ').');
        }

        // Step 3: Ensure schedules exist
        if (Schedule::count() === 0) {
            $this->command->info('No schedules found, creating weekly timetables...');
            $this->createSchedules();
        } else {
            $this->command->info('Schedules already exist (' . Schedule::count() . ').');
        }

        // Step 4: Generate attendance for June 2026
        $this->command->info('Generating attendance records for June 2026...');
        $this->generateAttendance();

        $this->command->info('Done. Total attendance records: ' . Attendance::count());
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Step 2 – Create CST Assignments
    // ─────────────────────────────────────────────────────────────────────
    private function createCSTAssignments($classes): void
    {
        $academicYear = '2025-2026';

        foreach ($classes as $class) {
            $levelSubjects = LevelSubject::where('level_id', $class->level_id)->get();

            if ($levelSubjects->isEmpty()) {
                $this->command->warn("No level subjects for class {$class->name}. Using all subjects as fallback.");
                $subjects = Subject::all();
                foreach ($subjects as $subject) {
                    $this->upsertCST($class, $subject->id, $class->main_teacher_id ?? Teacher::first()?->id, $academicYear, 1);
                }
                continue;
            }

            foreach ($levelSubjects as $ls) {
                $teacher = $this->findTeacherForSubject($ls->subject_id, $class);
                if (!$teacher) {
                    $this->command->warn("No teacher for subject {$ls->subject_id} in class {$class->name}. Skipping.");
                    continue;
                }
                $this->upsertCST($class, $ls->subject_id, $teacher->id, $academicYear, $ls->coefficient ?? 1);
            }
        }

        $this->command->info('CST assignments created: ' . ClassSubjectTeacher::count());
    }

    private function upsertCST($class, int $subjectId, ?int $teacherId, string $academicYear, int $coeff): void
    {
        if (!$teacherId) {
            return;
        }

        $exists = ClassSubjectTeacher::where('class_id', $class->id)
            ->where('subject_id', $subjectId)
            ->where('academic_year', $academicYear)
            ->exists();

        if (!$exists) {
            ClassSubjectTeacher::create([
                'class_id'      => $class->id,
                'subject_id'    => $subjectId,
                'teacher_id'    => $teacherId,
                'academic_year' => $academicYear,
                'coefficient'   => $coeff,
            ]);
        }
    }

    private function findTeacherForSubject(int $subjectId, $class): ?Teacher
    {
        $teacherIds = TeacherSubject::where('subject_id', $subjectId)->pluck('teacher_id');

        if ($teacherIds->isNotEmpty()) {
            if ($class->main_teacher_id && $teacherIds->contains($class->main_teacher_id)) {
                return Teacher::find($class->main_teacher_id);
            }
            return Teacher::whereIn('id', $teacherIds)->first();
        }

        if ($class->main_teacher_id) {
            return Teacher::find($class->main_teacher_id);
        }

        return Teacher::first();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Step 3 – Create Schedules
    // ─────────────────────────────────────────────────────────────────────
    private function createSchedules(): void
    {
        $rooms = ['A101', 'A102', 'B201', 'B202', 'C301', 'C302', 'D401', 'D402'];
        $roomIdx = 0;

        $allCST = ClassSubjectTeacher::all()->groupBy('class_id');

        foreach ($allCST as $classId => $cstRecords) {
            $daySlots = [];
            foreach (self::SCHOOL_DAYS as $day) {
                foreach (array_keys(self::TIME_SLOTS) as $slotIdx) {
                    $daySlots[] = [$day, $slotIdx];
                }
            }
            shuffle($daySlots);
            $comboIdx = 0;

            foreach ($cstRecords as $cst) {
                $weeklyCount = $this->getWeeklySessionCount($cst);

                for ($s = 0; $s < $weeklyCount; $s++) {
                    if ($comboIdx >= count($daySlots)) {
                        $comboIdx = 0;
                    }

                    [$day, $slotIdx] = $daySlots[$comboIdx++];
                    $slot = self::TIME_SLOTS[$slotIdx];
                    $room = $rooms[$roomIdx % count($rooms)];
                    $roomIdx++;

                    $exists = Schedule::where('class_subject_teacher_id', $cst->id)
                        ->where('day', $day)
                        ->where('start_time', $slot[0])
                        ->exists();

                    if (!$exists) {
                        Schedule::create([
                            'class_subject_teacher_id' => $cst->id,
                            'day'                      => $day,
                            'start_time'               => $slot[0],
                            'end_time'                 => $slot[1],
                            'room'                     => $room,
                        ]);
                    }
                }
            }
        }

        $this->command->info('Schedules created: ' . Schedule::count());
    }

    private function getWeeklySessionCount($cst): int
    {
        $class = DB::table('classes')->select('level_id')->where('id', $cst->class_id)->first();
        if (!$class) {
            return 2;
        }
        $ls = LevelSubject::where('level_id', $class->level_id)
            ->where('subject_id', $cst->subject_id)
            ->first();

        return $ls?->weekly_sessions_required ?? 2;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Step 4 – Generate Attendance for June 2026
    // ─────────────────────────────────────────────────────────────────────
    private function generateAttendance(): void
    {
        $juneSchoolDays = $this->getJuneSchoolDays();

        $dayLabels = array_map(fn ($d) => $d->format('d/m/Y (D)'), $juneSchoolDays);
        $this->command->info('School days: ' . implode(', ', $dayLabels));

        $schedules = Schedule::with('assignment')->get()->groupBy('day');

        if ($schedules->isEmpty()) {
            $this->command->error('No schedules available to generate attendance.');
            return;
        }

        $studentsByClass = Student::where('is_active', true)->get()->groupBy('class_id');

        $batch       = [];
        $batchSize   = 500;
        $totalInserted = 0;
        $now         = now();

        foreach ($juneSchoolDays as $date) {
            $dayName = $date->format('l');

            if (!isset($schedules[$dayName])) {
                continue;
            }

            foreach ($schedules[$dayName] as $schedule) {
                $assignment = $schedule->assignment;
                if (!$assignment) {
                    continue;
                }

                $classId   = $assignment->class_id;
                $subjectId = $assignment->subject_id;
                $teacherId = $assignment->teacher_id;

                $classStudents = $studentsByClass->get($classId, collect());

                foreach ($classStudents as $student) {
                    $status = $this->pickStatus();
                    $reason = null;
                    $time   = null;

                    if ($status === 'late') {
                        $time = Carbon::parse($schedule->start_time)->addMinutes(rand(5, 30))->format('H:i');
                    } elseif ($status === 'absent' || $status === 'excused') {
                        $reason = self::ABSENCE_REASONS[array_rand(self::ABSENCE_REASONS)];
                    }

                    $batch[] = [
                        'student_id'  => $student->id,
                        'subject_id'  => $subjectId,
                        'teacher_id'  => $teacherId,
                        'schedule_id' => $schedule->id,
                        'date'        => $date->toDateString(),
                        'status'      => $status,
                        'time'        => $time,
                        'reason'      => $reason,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::table('attendances')->insert($batch);
                        $totalInserted += count($batch);
                        $batch = [];
                        $this->command->info("Inserted {$totalInserted} records so far...");
                    }
                }
            }
        }

        if (!empty($batch)) {
            DB::table('attendances')->insert($batch);
            $totalInserted += count($batch);
        }

        $this->command->info("Total attendance records inserted: {$totalInserted}");
    }

    private function getJuneSchoolDays(): array
    {
        $days    = [];
        $current = Carbon::create(2026, 6, 1);
        $end     = Carbon::create(2026, 6, 30);

        // 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu
        $schoolDayNums = [0, 1, 2, 3, 4];

        while ($current->lte($end)) {
            if (in_array($current->dayOfWeek, $schoolDayNums)) {
                $days[] = $current->copy();
            }
            $current->addDay();
        }

        return $days;
    }

    private function pickStatus(): string
    {
        $rand       = rand(1, 100);
        $cumulative = 0;

        foreach (self::STATUS_WEIGHTS as $status => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return 'present';
    }
}
