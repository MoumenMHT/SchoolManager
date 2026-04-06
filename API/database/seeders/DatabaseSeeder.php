<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Level;
use App\Models\LevelSubject;
use App\Models\Fee;
use App\Models\Contract;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ParentFee;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

        if (User::where('email', 'admin@schoolmanager.com')->exists()) {
            $this->command->info('Seed baseline already exists, skipping duplicate seeding.');
            return;
        }

        // Create admin user
        $this->command->info('Creating admin user...');
        \App\Models\User::create([
            'username' => 'Admin User',
            'email' => 'admin@schoolmanager.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'admin',
            'phone' => '+212600000000',
            'address' => 'Admin Address',
            'is_active' => true,
        ]);

        // Create subjects
        $this->command->info('Creating subjects...');
        $subjects = [
            ['name' => 'Arabic', 'code' => 'AR', 'description' => 'Arabic language'],
            ['name' => 'Mathematics', 'code' => 'MATH', 'description' => 'Mathematics curriculum'],
            ['name' => 'Physics', 'code' => 'PHYS', 'description' => 'Physics curriculum'],
            ['name' => 'Islamic Studies', 'code' => 'ISL', 'description' => 'Islamic Studies curriculum'],
            ['name' => 'Civics', 'code' => 'CIV', 'description' => 'Civics curriculum'],
            ['name' => 'French', 'code' => 'FR', 'description' => 'French language'],
            ['name' => 'English', 'code' => 'EN', 'description' => 'English language'],
            ['name' => 'History and Geography', 'code' => 'HISGEO', 'description' => 'History and Geography curriculum'],
            ['name' => 'Natural Sciences', 'code' => 'NS', 'description' => 'Natural Sciences curriculum'],
            ['name' => 'Informatics', 'code' => 'INFO', 'description' => 'Informatics curriculum'],
            ['name' => 'Sports', 'code' => 'SP', 'description' => 'Physical Education and Sports'],
        ];

        $createdSubjects = [];
        foreach ($subjects as $subject) {
            $createdSubjects[] = \App\Models\Subject::create($subject);
        }

        // Create levels
        $this->command->info('Creating levels...');
        $levels = [
            ['name' => '1er', 'cycle' => 'cem', 'year_number' => 1, 'track' => null, 'sort_order' => 6],
            ['name' => '2em', 'cycle' => 'cem', 'year_number' => 2, 'track' => null, 'sort_order' => 7],
            ['name' => '3em', 'cycle' => 'cem', 'year_number' => 3, 'track' => null, 'sort_order' => 8],
            ['name' => '4em', 'cycle' => 'cem', 'year_number' => 4, 'track' => null, 'sort_order' => 9],
            
        ];

        $createdLevelsByName = [];
        foreach ($levels as $levelData) {
            $level = Level::create([
                'name' => $levelData['name'],
                'cycle' => $levelData['cycle'],
                'year_number' => $levelData['year_number'],
                'track' => $levelData['track'],
                'sort_order' => $levelData['sort_order'],
                'is_active' => true,
            ]);
            $createdLevelsByName[$level->name] = $level;
        }

        // Create level-subject configuration (replacement for subject_coefficients)
        $this->command->info('Creating level subjects configuration...');
        // Personalize weekly_hours for each subject/level here:
        $personalizedWeeklyHours = [
            '1er' => [
                'AR' => 5, 'MATH' => 4, 'PHYS' => 2, 'ISL' => 1, 'CIV' => 1, 'FR' => 4, 'EN' => 2, 'HISGEO' => 2, 'NS' => 2 , 'INFO' => 1, 'SP' => 2,
            ],
            '2em' => [
                'AR' => 5, 'MATH' => 4, 'PHYS' => 2, 'ISL' => 1, 'CIV' => 1, 'FR' => 4, 'EN' => 2, 'HISGEO' => 2, 'NS' => 2 , 'INFO' => 1, 'SP' => 2,
            ],
            '3em' => [
                'AR' => 4, 'MATH' => 4, 'PHYS' => 2, 'ISL' => 1, 'CIV' => 1, 'FR' => 4, 'EN' => 3, 'HISGEO' => 2, 'NS' => 2 , 'INFO' => 1, 'SP' => 2,
            ],
            '4em' => [
                'AR' => 4, 'MATH' => 4, 'PHYS' => 2, 'ISL' => 1, 'CIV' => 1, 'FR' => 4, 'EN' => 2, 'HISGEO' => 2, 'NS' => 2 , 'INFO' => 1, 'SP' => 2,
            ],
        ];

        foreach ($createdSubjects as $subject) {
            foreach ($createdLevelsByName as $level) {
                $weeklyHours = $personalizedWeeklyHours[$level->name][$subject->code] ?? $this->getWeeklySessionsForSubject($subject->code);
                LevelSubject::create([
                    'level_id' => $level->id,
                    'subject_id' => $subject->id,
                    'coefficient' => rand(1, 4),
                    'weekly_sessions_required' => $weeklyHours,
                    'weekly_hours' => $weeklyHours,
                ]);
            }
        }

        // Create teachers: 2 teachers per subject
        // Teacher 1 handles levels 1-2, Teacher 2 handles levels 3-4.
        $teacherCount = count($createdSubjects) * 2;
        $this->command->info('Creating ' . $teacherCount . ' teachers (2 per subject)...');
        $teachers = [];
        $subjectTeacherByBand = [];
        $teacherIndex = 1;

        foreach ($createdSubjects as $subject) {
            foreach (['lower', 'upper'] as $band) {
                $teacherUser = \App\Models\User::create([
                    'username' => fake()->name(),
                    'email' => 'teacher' . $teacherIndex . '@schoolmanager.com',
                    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                    'role' => 'teacher',
                    'phone' => '+213' . fake()->numerify('6########'),
                    'address' => fake()->address(),
                    'is_active' => true,
                ]);

                $teacher = \App\Models\Teacher::create([
                    'user_id' => $teacherUser->id,
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'cin' => fake()->unique()->regexify('[A-Z]{2}[0-9]{6}'),
                    'birth_date' => fake()->date('Y-m-d', '-30 years'),
                    'specialization' => $subject->name,
                    'hire_date' => fake()->date('Y-m-d', '-5 years'),
                    'salary' => fake()->randomFloat(2, 3000, 8000),
                    'contract_type' => 'permanent',
                    'weekly_hours' => 20,
                ]);

                $teachers[] = $teacher;
                $subjectTeacherByBand[$subject->id][$band] = $teacher->id;

                foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'] as $day) {
                    \App\Models\TeacherAvailability::create([
                        'teacher_id' => $teacher->id,
                        'day' => $day,
                        'start_time' => '08:00',
                        'end_time' => '16:00',
                    ]);
                }

                \App\Models\TeacherSubject::create([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                ]);

                $teacherIndex++;
            }
        }

        //create supervisors (5 supervisors)
        $this->command->info('Creating 5 supervisors...');
        $supervisors = [];
        for ($i = 0; $i < 5; $i++) {
            $supervisorUser = \App\Models\User::create([
                'username' => fake()->name(),
                'email' => 'supervisor' . ($i + 1) . '@schoolmanager.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'supervisor',
                'phone' => '+213' . fake()->numerify('6########'),
                'address' => fake()->address(),
                'is_active' => true,
            ]);

            $supervisor = \App\Models\Supervisor::create([
                'user_id' => $supervisorUser->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'phone' => fake()->boolean(50) ? '+213' . fake()->numerify('6########') : null,
                'hire_date' => fake()->date('Y-m-d', '-10 years'),
                'status' => fake()->boolean(80) ? 'active' : 'inactive',
            ]);

            $supervisors[] = $supervisor;
            
        }



        // Create parents (50 parents, half with accounts)
        $this->command->info('Creating 50 parents...');
        $parents = [];
        
        for ($i = 0; $i < 50; $i++) {
            $hasAccount = $i < 25; // First 25 have accounts
            $parentUser = null;

            if ($hasAccount) {
                $parentUser = \App\Models\User::create([
                    'username' => fake()->name(),
                    'email' => 'parent' . ($i + 1) . '@schoolmanager.com',
                    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                    'role' => 'parent',
                    'phone' => '+213' . fake()->numerify('6########'),
                    'address' => fake()->address(),
                    'is_active' => true,
                ]);
            }

            $parent = \App\Models\ParentModel::create([
                'user_id' => $parentUser?->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'cin' => fake()->unique()->regexify('[A-Z]{2}[0-9]{6}'),
                'phone' => $hasAccount ? null : '+213' . fake()->numerify('6########'),
                'email' => $hasAccount ? null : fake()->unique()->email(),
                'profession' => fake()->randomElement(['Engineer', 'Doctor', 'Teacher', 'Lawyer', 'Businessman', 'Accountant']),
            ]);

            $parents[] = $parent;
        }

        // Create classes (8 classes)
        $this->command->info('Creating 8 classes...');
        $classes = [];
        $classNames = ['A', 'B', 'C', 'D'];
        
        foreach (array_keys($createdLevelsByName) as $levelName) {
            foreach (array_slice($classNames, 0, 2) as $name) {
                $class = \App\Models\SchoolClass::create([
                    'name' => $levelName . ' ' . $name,
                    'level' => $levelName,
                    'level_id' => $createdLevelsByName[$levelName]->id,
                    'academic_year' => '2025-2026',
                    'capacity' => 30,
                    'main_teacher_id' => $teachers[array_rand($teachers)]->id,
                    'is_active' => true,
                ]);
                $classes[] = $class;
            }
        }

        // Create students (150 students)
        $this->command->info('Creating 150 students...');
        $students = [];
        
        for ($i = 0; $i < 150; $i++) {
            $student = \App\Models\Student::create([
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'code' => 'STU' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'birth_date' => fake()->date('Y-m-d', '-12 years'),
                'gender' => fake()->randomElement(['male', 'female']),
                'class_id' => $classes[array_rand($classes)]->id,
                'parent_id' => $parents[array_rand($parents)]->id,
                'enrollment_date' => fake()->date('Y-m-d', '-2 years'),
                'medical_info' => fake()->optional(0.3)->sentence(),
                'is_active' => true,
            ]);
            $students[] = $student;
        }
        // Assign supervisors to classes (one class per supervisor)
        $availableClassIds = collect($classes)->pluck('id')->shuffle()->values();
        $supervisors = \App\Models\Supervisor::all();

        foreach ($supervisors as $index => $supervisor) {
            if ($index >= $availableClassIds->count()) {
                break;
            }

            $supervisor->update([
                'class_id' => $availableClassIds->get($index),
            ]);
        }

        $levelYearById = [];
        foreach ($createdLevelsByName as $level) {
            $levelYearById[$level->id] = $level->year_number;
        }

        // Create class-subject-teacher assignments
        $this->command->info('Creating class assignments...');
        $assignments = [];
        
        foreach ($classes as $class) {
            $yearNumber = $levelYearById[$class->level_id] ?? null;
            $band = $yearNumber !== null && $yearNumber <= 2 ? 'lower' : 'upper';

            // Every class studies every subject.
            foreach ($createdSubjects as $subject) {
                $teacherId = $subjectTeacherByBand[$subject->id][$band] ?? null;
                if ($teacherId === null) {
                    continue;
                }

                $assignment = \App\Models\ClassSubjectTeacher::create([
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacherId,
                    'academic_year' => '2025-2026',
                ]);
                $assignments[] = $assignment;
            }
        }

     


        // Create grades for all students across all subjects and exam types
        $this->command->info('Creating comprehensive grade records for all students...');
        $examTypes = ['Quiz', 'Midterm', 'Final', 'Homework', 'Project'];
        $semesters = ['Trimester 1', 'Trimester 2', 'Trimester 3'];

        // Group assignments by class_id to quickly find the student's teachers and subjects
        $assignmentsByClass = [];
        foreach ($assignments as $assignment) {
            $assignmentsByClass[$assignment->class_id][] = $assignment;
        }

        foreach ($students as $student) {
            $studentAssignments = $assignmentsByClass[$student->class_id] ?? [];

            foreach ($studentAssignments as $assignment) {
                foreach ($semesters as $semester) {
                    foreach ($examTypes as $examType) {
                        \App\Models\Grade::create([
                            'student_id' => $student->id,
                            'subject_id' => $assignment->subject_id,
                            'teacher_id' => $assignment->teacher_id,
                            'exam_type' => $examType,
                            'grade' => fake()->randomFloat(2, 0, 20),
                            'max_grade' => 20,
                            'semester' => $semester,
                            'academic_year' => '2025-2026',
                            'comment' => fake()->optional(0.3)->sentence(),
                        ]);
                    }
                }
            }
        }



        // ============================================================
        // PAYMENT SYSTEM SEED DATA  –  Academic Year 2025-2026
        // ============================================================
        $this->command->info('Setting up payment system...');

        // ── Phase 1: Fees ─────────────────────────────────────────
        // Current year (active)
        $feeTuition  = Fee::create(['name' => 'Tuition Fee',    'description' => 'Monthly academic tuition',          'base_amount' => 3500.00, 'academic_year' => '2025-2026', 'is_active' => true]);
        $feeTransport = Fee::create(['name' => 'Transportation', 'description' => 'School bus transportation service', 'base_amount' => 800.00,  'academic_year' => '2025-2026', 'is_active' => true]);
        $feeLunch    = Fee::create(['name' => 'Lunch Program',   'description' => 'Daily cafeteria meals program',     'base_amount' => 600.00,  'academic_year' => '2025-2026', 'is_active' => true]);
        $feeActivity = Fee::create(['name' => 'Activity Fee',   'description' => 'Sports and extracurricular',        'base_amount' => 400.00,  'academic_year' => '2025-2026', 'is_active' => true]);
        $feeLibrary  = Fee::create(['name' => 'Library Fee',    'description' => 'Library access and materials',      'base_amount' => 250.00,  'academic_year' => '2025-2026', 'is_active' => true]);

        // Attach fees to levels
        $allLevelIds = array_values(array_map(fn ($level) => $level->id, $createdLevelsByName));
        foreach ([$feeTuition, $feeTransport, $feeLunch, $feeActivity, $feeLibrary] as $fee) {
            $fee->levels()->syncWithoutDetaching($allLevelIds);
        }

        // Previous year (archived / inactive)
        foreach ([
            ['Tuition Fee',    3200.00],
            ['Transportation', 700.00],
            ['Lunch Program',  550.00],
            ['Activity Fee',   350.00],
            ['Library Fee',    200.00],
        ] as [$fname, $famount]) {
            Fee::create(['name' => $fname, 'description' => $fname, 'base_amount' => $famount, 'academic_year' => '2024-2025', 'is_active' => false]);
        }

        // ── Phase 2: Academic-year helpers ────────────────────────
        $academicYear  = '2025-2026';
        $contractStart = Carbon::parse('2025-09-01');
        $contractEnd   = Carbon::parse('2026-06-30');
        $totalMonths   = 10;

        // Build the 10 billing months: [Sep 2025 … Jun 2026]
        $billMonths = [];
        for ($m = 0; $m < $totalMonths; $m++) {
            $billMonths[] = $contractStart->copy()->addMonths($m)->startOfMonth();
        }

        // ── Phase 3: Seed-contract helper closure ─────────────────
        // WithoutModelEvents disables the Contract boot() auto-generator,
        // so we generate contract_number explicitly via a shared counter.
        $contractSeq = 0;

        /**
         * Creates one contract + 10 bills + payment transactions + allocations.
         *
         * @param $parent          Eloquent Parent model
         * @param array  $fees     Array of Fee models included in this contract
         * @param float  $discPct  Discount percentage (0 = none)
         * @param ?string $discReason
         * @param int    $paidFull  How many monthly bills are fully paid (0-10)
         * @param ?array $partial   null  OR  [absoluteMonthIndex, fraction]
         *                          e.g. [2, 0.5] = month index 2 is 50 % paid
         * @param bool   $advanceFull  true = one single advance payment covers all 10 months
         * @param float  $overpay  Extra DZD added on top of the last payment (stored in balance)
         * @param string $notes
         * @param string $method   Payment type string
         */
        $seedContract = function (
            $parent,
            array $fees,
            float $discPct,
            ?string $discReason,
            int   $paidFull,
            ?array $partial,
            bool  $advanceFull,
            float $overpay,
            string $notes,
            string $method
        ) use ($billMonths, $academicYear, $contractStart, $contractEnd, $totalMonths, &$contractSeq) {

            $grossMonthly = (float) array_sum(array_map(fn($f) => (float) $f->base_amount, $fees));
            $grossTotal   = $grossMonthly * $totalMonths;
            $discAmt      = round($grossTotal * $discPct / 100, 2);
            $netTotal     = $grossTotal - $discAmt;
            $netMonthly   = round($netTotal / $totalMonths, 2);

            // Calculate paid_amount and balance
            $paidFromBills = $advanceFull
                ? $netTotal
                : $netMonthly * $paidFull + ($partial ? round($netMonthly * $partial[1], 2) : 0);
            $paidAmount = $paidFromBills + $overpay;

            $contract = Contract::create([
                'parent_id'        => $parent->id,
                'contract_number'  => 'CNT-2025-' . str_pad(++$contractSeq, 6, '0', STR_PAD_LEFT),
                'academic_year'    => $academicYear,
                'total_fees'       => $grossTotal,
                'discount_type'    => $discPct > 0 ? 'percentage' : null,
                'discount_value'   => $discAmt,
                'discount_reason'  => $discReason,
                'monthly_amount'   => $netMonthly,
                'paid_amount'      => $paidAmount,
                'remaining_amount' => max(0, $netTotal - $paidFromBills),
                'balance'          => $overpay,
                'start_date'       => $contractStart,
                'end_date'         => $contractEnd,
                'status'           => 'active',
                'notes'            => $notes,
            ]);

            // Parent ↔ Fee link
            foreach ($fees as $fee) {
                ParentFee::create(['parent_id' => $parent->id, 'fee_id' => $fee->id]);
            }

            // Bills
            $bills = [];
            $now = Carbon::now();
            foreach ($billMonths as $i => $month) {
                $dueDate   = $month->copy()->endOfMonth();
                $isPastDue = $dueDate->lt($now);

                if ($advanceFull || $i < $paidFull) {
                    $amtPaid = $netMonthly;
                    $status  = 'paid';
                } elseif ($partial && $i === $partial[0]) {
                    $amtPaid = round($netMonthly * $partial[1], 2);
                    $status  = 'partial';
                } else {
                    $amtPaid = 0;
                    $status  = $isPastDue ? 'late' : 'unpaid';
                }

                $bills[] = Bill::create([
                    'contract_id' => $contract->id,
                    'month_year'  => $month->format('F Y'),
                    'amount_due'  => $netMonthly,
                    'amount_paid' => $amtPaid,
                    'balance'     => $netMonthly - $amtPaid,
                    'status'      => $status,
                    'due_date'    => $dueDate,
                ]);
            }

            // Payments & allocations
            $allocate = function ($paymentId, $bill, $amount) {
                PaymentAllocation::create([
                    'payment_id' => $paymentId,
                    'bill_id'    => $bill->id,
                    'amount'     => $amount,
                ]);
            };

            if ($advanceFull) {
                // One lump-sum advance at the start of the year
                $p = Payment::create([
                    'contract_id'  => $contract->id,
                    'amount'       => $netTotal,
                    'payment_type' => $method,
                    'paid_date'    => $billMonths[0]->copy()->setDay(3),
                    'status'       => 'completed',
                    'note'         => 'Full advance payment – entire academic year',
                ]);
                foreach ($bills as $bill) {
                    $allocate($p->id, $bill, $netMonthly);
                }

            } elseif ($paidFull > 3) {
                // Advance for first 3 months, then individual monthly payments
                $p1 = Payment::create([
                    'contract_id'  => $contract->id,
                    'amount'       => $netMonthly * 3,
                    'payment_type' => $method,
                    'paid_date'    => $billMonths[0]->copy()->setDay(5),
                    'status'       => 'completed',
                    'note'         => 'Advance payment – 3 months (Sep–Nov)',
                ]);
                for ($i = 0; $i < 3; $i++) {
                    $allocate($p1->id, $bills[$i], $netMonthly);
                }
                for ($i = 3; $i < $paidFull; $i++) {
                    $isLast    = ($i === $paidFull - 1);
                    $extraAmt  = $isLast ? $overpay : 0;
                    $p = Payment::create([
                        'contract_id'  => $contract->id,
                        'amount'       => $netMonthly + $extraAmt,
                        'payment_type' => $method,
                        'paid_date'    => $billMonths[$i]->copy()->setDay(5),
                        'status'       => 'completed',
                        'note'         => 'Monthly payment – ' . $billMonths[$i]->format('F Y')
                            . ($extraAmt > 0 ? ' (includes credit)' : ''),
                    ]);
                    $allocate($p->id, $bills[$i], $netMonthly);
                }

            } elseif ($paidFull > 0) {
                // One payment covers all fully-paid months
                $p = Payment::create([
                    'contract_id'  => $contract->id,
                    'amount'       => $netMonthly * $paidFull + $overpay,
                    'payment_type' => $method,
                    'paid_date'    => $billMonths[0]->copy()->setDay(5),
                    'status'       => 'completed',
                    'note'         => $paidFull === 1
                        ? 'First month payment – ' . $billMonths[0]->format('F Y')
                        : 'Payment for ' . $paidFull . ' months',
                ]);
                for ($i = 0; $i < $paidFull; $i++) {
                    $allocate($p->id, $bills[$i], $netMonthly);
                }
            }

            // Partial month payment (separate transaction)
            if ($partial) {
                $pi   = $partial[0];      // absolute month index
                $frac = $partial[1];
                $partAmt = round($netMonthly * $frac, 2);
                $pp = Payment::create([
                    'contract_id'  => $contract->id,
                    'amount'       => $partAmt,
                    'payment_type' => $method,
                    'paid_date'    => $billMonths[$pi]->copy()->setDay(8),
                    'status'       => 'completed',
                    'note'         => 'Partial payment – ' . $billMonths[$pi]->format('F Y'),
                ]);
                $allocate($pp->id, $bills[$pi], $partAmt);
            }

            return $contract;
        };

        // ── Phase 4: 20 Contracts ─────────────────────────────────
        $this->command->info('Creating 20 contracts with payment history...');

        /**
         * Scenario columns:
         * [parentIdx, fees[], discPct, discReason, paidFull, partial, advanceFull, overpay, notes, method]
         *
         * today = 2026-03-06
         * Past-due months: Sep–Feb (indices 0–5), future: Mar–Jun (indices 6–9)
         *
         * Groups:
         *  G1 (idx 0-4)   Fully up-to-date  – paid Sep … Feb (6 months)
         *  G2 (idx 5-8)   Slightly behind   – paid Sep … Jan (5), Feb late
         *  G3 (idx 9-12)  Significantly behind – paid Sep–Oct (2), Nov–Feb late
         *  G4 (idx 13-15) Minimal payers    – paid Sep only (1), Oct–Feb late
         *  G5 (idx 16-17) Advance all-year  – paid all 10 months upfront
         *  G6 (idx 18)    Partial payment   – Sep–Oct full, Nov half, Dec–Feb late
         *  G7 (idx 19)    Overpayment       – paid Sep–Feb + 600 DZD credit
         */
        $scenarios = [
            // ── G1: Up-to-date ──────────────────────────────────────────────────────────────────────────────────────────────── paidFull  partial  adv    overpay
            [0,  [$feeTuition, $feeTransport, $feeLunch],                           0,    null,                            6, null,     false, 0.0,   'Contract: tuition + transport + lunch – paid up to date',     'bank_transfer'],
            [1,  [$feeTuition, $feeTransport, $feeLunch],                          10,    'Sibling discount – 2 enrolled', 6, null,     false, 0.0,   '10 % sibling discount – 2 children',                          'cash'],
            [2,  [$feeTuition, $feeTransport],                                      0,    null,                            6, null,     false, 0.0,   'Tuition + transport – monthly bank transfer',                  'cheque'],
            [3,  [$feeTuition, $feeLunch],                                          0,    null,                            6, null,     false, 0.0,   'Tuition + lunch program – up to date',                         'bank_transfer'],
            [4,  [$feeTuition, $feeTransport, $feeLunch, $feeActivity],             0,    null,                            6, null,     false, 0.0,   'Full service contract – 4 fees, up to date',                   'online'],

            // ── G2: Slightly behind (missing Feb) ───────────────────────────────────────────────────────────────────────────
            [5,  [$feeTuition, $feeTransport, $feeLunch],                           0,    null,                            5, null,     false, 0.0,   'Behind 1 month – February overdue',                            'cash'],
            [6,  [$feeTuition, $feeTransport],                                      0,    null,                            5, null,     false, 0.0,   'Tuition + transport – missing February',                        'bank_transfer'],
            [7,  [$feeTuition, $feeLunch],                                          0,    null,                            5, null,     false, 0.0,   'Tuition + lunch – missing February',                           'cash'],
            [8,  [$feeTuition, $feeActivity],                                       0,    null,                            5, null,     false, 0.0,   'Tuition + activity – missing February',                        'cheque'],

            // ── G3: Significantly behind (Nov–Feb overdue) ──────────────────────────────────────────────────────────────────
            [9,  [$feeTuition, $feeTransport, $feeLunch],                           5,    'Financial hardship assistance', 2, null,     false, 0.0,   '5 % hardship discount – 4 months overdue',                     'cash'],
            [10, [$feeTuition, $feeTransport],                                      0,    null,                            2, null,     false, 0.0,   'Tuition + transport – 4 months overdue',                       'cash'],
            [11, [$feeTuition],                                                     0,    null,                            2, null,     false, 0.0,   'Tuition only – 4 months overdue',                              'cash'],
            [12, [$feeTuition, $feeLunch],                                          0,    null,                            2, null,     false, 0.0,   'Tuition + lunch – 4 months overdue',                           'cash'],

            // ── G4: Minimal payers (Oct–Feb overdue) ────────────────────────────────────────────────────────────────────────
            [13, [$feeTuition, $feeTransport, $feeLunch],                           0,    null,                            1, null,     false, 0.0,   'Only first month paid – 5 months overdue',                     'cash'],
            [14, [$feeTuition],                                                     0,    null,                            1, null,     false, 0.0,   'Tuition only – 5 months overdue',                              'cash'],
            [15, [$feeTuition, $feeTransport],                                      0,    null,                            1, null,     false, 0.0,   'Tuition + transport – 5 months overdue',                       'bank_transfer'],

            // ── G5: Advance full payment ─────────────────────────────────────────────────────────────────────────────────────
            [16, [$feeTuition, $feeTransport, $feeLunch, $feeActivity, $feeLibrary], 0,   null,                           10, null,     true,  0.0,   'Full advance – all 5 services paid for the year',              'bank_transfer'],
            [17, [$feeTuition, $feeTransport, $feeLunch],                            0,   null,                           10, null,     true,  0.0,   'Full advance – 3 services paid for the year',                  'cheque'],

            // ── G6: Partial payment ──────────────────────────────────────────────────────────────────────────────────────────
            // paidFull=2 (Sep+Oct), then partial on month index 2 (Nov, 50%)
            [18, [$feeTuition, $feeTransport, $feeLunch],                           15,   'Financial assistance grant',   2, [2, 0.5], false, 0.0,   '15 % discount – partial payment on November',                  'cash'],

            // ── G7: Overpayment (600 DZD credit balance) ────────────────────────────────────────────────────────────────────
            [19, [$feeTuition, $feeTransport, $feeLunch],                           10,   'Sibling discount',             6, null,     false, 600.0, '10 % sibling discount – overpaid by 600 DZD',                  'bank_transfer'],
        ];

        $contractCount = 0;
        foreach ($scenarios as $s) {
            [
                $pIdx, $fees, $discPct, $discReason, $paidFull,
                $partial, $advanceFull, $overpay, $notes, $method
            ] = $s;

            $seedContract(
                $parents[$pIdx], $fees, $discPct, $discReason,
                $paidFull, $partial, $advanceFull, $overpay, $notes, $method
            );
            $contractCount++;
        }

        // ── Summary ───────────────────────────────────────────────
        $totalContracts = Contract::count();
        $totalPayments  = Payment::count();
        $totalBills     = Bill::count();

        $this->command->info('');
        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('📊 Summary:');
        $this->command->info('   • 1 Admin user');
        $this->command->info('   • ' . $teacherCount . ' Teachers (2 per subject)');
        $this->command->info('   • 50 Parents (25 with portal accounts)');
        $this->command->info('   • 150 Students across 8 classes');
        $this->command->info('   • 10 Subjects with level configuration (coefficient + weekly sessions)');
        $this->command->info('   • 300 Attendance records');
        $this->command->info('   • ' . \App\Models\Grade::count() . ' Grade records');
        $this->command->info('   • 5 Fees (2025-2026) + 5 archived (2024-2025)');
        $this->command->info('   • ' . $totalContracts . ' Contracts  (' . $contractCount . ' created this run)');
        $this->command->info('   • ' . $totalBills     . ' Bills');
        $this->command->info('   • ' . $totalPayments  . ' Payment transactions');
        $this->command->info('');
        $this->command->info('📋 Contract scenarios seeded:');
        $this->command->info('   G1 (5 contracts) – Fully up-to-date, Sep–Feb paid');
        $this->command->info('   G2 (4 contracts) – Slightly behind, Feb overdue');
        $this->command->info('   G3 (4 contracts) – 4 months overdue (Nov–Feb)');
        $this->command->info('   G4 (3 contracts) – 5 months overdue (Oct–Feb)');
        $this->command->info('   G5 (2 contracts) – Advance payment, all 10 months');
        $this->command->info('   G6 (1 contract)  – Partial payment on November');
        $this->command->info('   G7 (1 contract)  – Overpayment, 600 DZD credit');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔐 Login credentials:');
        $this->command->info('   Admin:    admin@schoolmanager.com / password123');
        $this->command->info('   Teachers: teacher1-' . $teacherCount . '@schoolmanager.com  / password123');
        $this->command->info('   Parents:  parent1-25@schoolmanager.com   / password123');
    }

    /**
     * Return a recent date (within the last 4 weeks) that falls on the given day name.
     */
    private function recentDateForDay(string $day): string
    {
        $dayMap = [
            'Sunday'    => 0,
            'Monday'    => 1,
            'Tuesday'   => 2,
            'Wednesday' => 3,
            'Thursday'  => 4,
            'Friday'    => 5,
            'Saturday'  => 6,
        ];

        $targetDow  = $dayMap[$day] ?? 1;
        $weeksBack  = rand(1, 4);
        $base       = Carbon::now()->subWeeks($weeksBack);
        $currentDow = $base->dayOfWeek; // 0=Sun ... 6=Sat
        $diff       = $targetDow - $currentDow;

        return $base->addDays($diff)->format('Y-m-d');
    }

    private function getWeeklySessionsForSubject(string $subjectCode): int
    {
        return match ($subjectCode) {
            'MATH' => 6,
            'AR', 'FR' => 5,
            'EN', 'PHYS', 'CHEM', 'BIO' => 3,
            default => 2,
        };
    }
}
