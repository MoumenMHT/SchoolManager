<?php

namespace Database\Seeders;

use App\Models\ClassSubjectTeacher;
use App\Models\Level;
use App\Models\LevelSubject;
use App\Models\ParentModel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use \Illuminate\Database\Console\Seeds\WithoutModelEvents;

    // ──────────────────────────────────────────────
    //  Static name pools (Arabic-Algerian style)
    // ──────────────────────────────────────────────
    private array $maleFirstNames = [
        'يوسف', 'أحمد', 'محمد', 'عمر', 'علي', 'إبراهيم', 'خالد', 'عبد الرحمن',
        'هارون', 'زكريا', 'ياسين', 'سليمان', 'إسماعيل', 'بلال', 'عثمان',
        'حمزة', 'طارق', 'نبيل', 'كريم', 'وليد', 'فارس', 'رياض', 'سامي',
        'ماهر', 'جمال', 'لقمان', 'هيثم', 'منير', 'أنيس', 'صلاح',
    ];

    private array $femaleFirstNames = [
        'فاطمة', 'مريم', 'خديجة', 'عائشة', 'زينب', 'سارة', 'نور', 'إيمان',
        'أسماء', 'حنان', 'سلمى', 'رنا', 'منى', 'هند', 'ليلى',
        'أميرة', 'وردة', 'رحمة', 'سناء', 'نجوى', 'إنصاف', 'وفاء', 'نادية',
        'لينا', 'ريم', 'دنيا', 'رانية', 'شيماء', 'ياسمين', 'بسمة',
    ];

    private array $lastNames = [
        'بن علي', 'بن عمر', 'بوعزيز', 'بلحاج', 'بن يوسف', 'حمادي', 'زروق',
        'قاسمي', 'مزاري', 'تومي', 'سعدي', 'بوديار', 'مصطفى', 'لعريبي',
        'بن عيسى', 'عبد الله', 'شريف', 'بوكرزازة', 'درويش', 'بوزيان',
        'بن سالم', 'فرحات', 'الأمين', 'حسيني', 'بن حمو', 'قريشي', 'بلوط',
        'حمدان', 'بن داود', 'صابري',
    ];

    private array $professions = [
        'طبيب', 'مهندس', 'أستاذ', 'محامٍ', 'تاجر', 'موظف', 'صيدلاني',
        'معلم', 'مقاول', 'فلاح', 'ميكانيكي', 'كهربائي', 'نجار', 'محاسب',
    ];

    private int $userCounter  = 1;
    private int $studentCodeCounter = 1000;

    public function run(): void
    {
        $this->command->info('🌱 Starting comprehensive database seeding...');

        if (User::where('username', 'admin')->exists()) {
            $this->command->warn('⚠ Seed already exists – dropping all data and re-seeding.');
            $this->truncateAll();
        }

        $this->disableFkChecks();
        DB::beginTransaction();
        try {
            // 1. Core staff
            $this->seedCoreStaff();

            // 2. Subjects
            [$primarySubjects, $cemSubjects, $allSubjects] = $this->seedSubjects();

            // 3. Levels  (5 primary + 4 CEM)
            [$primaryLevels, $cemLevels] = $this->seedLevels($allSubjects);

            // 4. Supervisors
            [$primarySupervisor, $cemSupervisors] = $this->seedSupervisors($cemLevels);

            // 5. Teachers
            [$primaryTeachers, $cemSubjectTeachers] = $this->seedTeachers(
                $primarySubjects, $cemSubjects
            );

            // 6. Classes  (10 primary + 8 CEM)
            [$primaryClasses, $cemClasses] = $this->seedClasses(
                $primaryLevels, $cemLevels,
                $primaryTeachers, $cemSubjectTeachers,
                $primarySupervisor, $cemSupervisors
            );

            // 7. Class–Subject–Teacher assignments
            $this->assignClassSubjectTeachers(
                $primaryClasses, $cemClasses,
                $primaryTeachers, $cemSubjectTeachers,
                $primarySubjects, $cemSubjects
            );

            // 8. Students & parents
            $this->seedStudentsAndParents($primaryClasses, $cemClasses);

            DB::commit();
            $this->command->info('✅ Base seed committed.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->enableFkChecks();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            $this->command->error($e->getTraceAsString());
            return;
        }
        $this->enableFkChecks();

        // 9. Exams, exercises, grades (all 3 trimesters)
        $this->call(ExamsAndGradesSeeder::class);

        // 10. Finances (contracts, bills, payments)
        $this->call(FinancesSeeder::class);

        $this->command->info('🎉 Full seed complete!');
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function disableFkChecks(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }
    }

    private function enableFkChecks(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    private function truncateAll(): void
    {
        $this->disableFkChecks();
        $tables = [
            'payment_allocations', 'payments', 'bills', 'contracts',
            'parents_fees', 'exercise_grades', 'grades', 'exam_exercises',
            'class_exam', 'exams', 'student_averages', 'student_history',
            'attendances', 'schedules', 'class_subject_teacher',
            'teacher_availabilities', 'teacher_subjects',
            'students', 'parents', 'classes', 'supervisors',
            'teachers', 'fee_levels', 'fees', 'level_subjects', 'levels',
            'subjects', 'personal_access_tokens', 'users',
        ];
        foreach ($tables as $t) {
            DB::table($t)->delete();
        }
        $this->enableFkChecks();
    }

    private function makeUser(string $username, string $role): User
    {
        return User::create([
            'username'  => $username,
            'email'     => $username . '@schoolhub.dz',
            'password'  => Hash::make('password123'),
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    private function randomMaleName(): array
    {
        $first = $this->maleFirstNames[array_rand($this->maleFirstNames)];
        $last  = $this->lastNames[array_rand($this->lastNames)];
        return ['first' => $first, 'last' => $last];
    }

    private function randomFemaleName(): array
    {
        $first = $this->femaleFirstNames[array_rand($this->femaleFirstNames)];
        $last  = $this->lastNames[array_rand($this->lastNames)];
        return ['first' => $first, 'last' => $last];
    }

    private function randomName(): array
    {
        return rand(0, 1) ? $this->randomMaleName() : $this->randomFemaleName();
    }

    private function algPhone(): string
    {
        $prefix = rand(0, 1) ? '06' : '07';
        return '+213' . $prefix . str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function nextUsername(string $prefix): string
    {
        return $prefix . ($this->userCounter++);
    }

    // ──────────────────────────────────────────────
    //  1. Core staff
    // ──────────────────────────────────────────────
    private function seedCoreStaff(): void
    {
        $this->command->info('👤 Creating core staff…');

        $this->makeUser('admin', 'admin');

        foreach (['secretariat', 'accountant'] as $role) {
            $this->makeUser($role, $role);
        }

        foreach (['primary', 'cem', 'lycee'] as $d) {
            $this->makeUser("director_{$d}", "{$d}_director");
        }
    }

    // ──────────────────────────────────────────────
    //  2. Subjects
    // ──────────────────────────────────────────────
    private function seedSubjects(): array
    {
        $this->command->info('📚 Creating subjects…');

        $subjectDefs = [
            // code => [name, primary_coeff, cem_coeff, primary_weekly, cem_weekly]
            'AR'      => ['اللغة العربية',              3, 3, 5, 5],
            'MATH'    => ['الرياضيات',                  3, 4, 5, 5],
            'FR'      => ['اللغة الفرنسية',             2, 3, 3, 4],
            'EN'      => ['اللغة الإنجليزية',           2, 2, 3, 3],
            'ISL'     => ['التربية الإسلامية',          2, 1, 2, 2],
            'CIV'     => ['التربية المدنية',            1, 1, 1, 1],
            'HISGEO'  => ['التاريخ والجغرافيا',         2, 2, 2, 3],
            'NS'      => ['ع الطبيعة والحياة',           2, 2, 2, 3],
            'PHYS'    => ['ع الفيزيائية والتكنولوجيا',   0, 3, 0, 3],
            'INFO'    => ['المعلوماتية',                 0, 1, 0, 2],
            'SP'      => ['ت البدنية والرياضة',          1, 1, 2, 2],
            'ART'     => ['التربية التشكيلية',           1, 0, 1, 0],
            'MUS'     => ['التربية الموسيقية',           1, 0, 1, 0],
            'SCI'     => ['ت العلمية والتكنولوجيا',      2, 0, 2, 0],
        ];

        // Primary uses all except PHYS and INFO
        $primaryCodes = ['AR', 'MATH', 'FR', 'EN', 'ISL', 'CIV', 'HISGEO', 'NS', 'SP', 'ART', 'MUS', 'SCI'];
        // CEM uses all except ART, MUS, SCI
        $cemCodes     = ['AR', 'MATH', 'FR', 'EN', 'ISL', 'CIV', 'HISGEO', 'NS', 'PHYS', 'INFO', 'SP'];

        $allSubjects     = [];
        $primarySubjects = [];
        $cemSubjects     = [];

        foreach ($subjectDefs as $code => [$name]) {
            $subject = Subject::create(['name' => $name, 'code' => $code]);
            $allSubjects[$code] = $subject;
        }

        foreach ($primaryCodes as $c) { $primarySubjects[$c] = $allSubjects[$c]; }
        foreach ($cemCodes     as $c) { $cemSubjects[$c]     = $allSubjects[$c]; }

        return [$primarySubjects, $cemSubjects, $allSubjects, $subjectDefs];
    }

    // ──────────────────────────────────────────────
    //  3. Levels
    // ──────────────────────────────────────────────
    private function seedLevels(array $allSubjects): array
    {
        $this->command->info('🏫 Creating levels…');

        // Primary: years 1–5  (we'll use 2 of them to host 10 classes)
        $primaryLevelDefs = [
            ['name' => 'الأولى ابتدائي',  'cycle' => 'primaire', 'year_number' => 1, 'sort_order' => 1],
            ['name' => 'الثانية ابتدائي', 'cycle' => 'primaire', 'year_number' => 2, 'sort_order' => 2],
            ['name' => 'الثالثة ابتدائي', 'cycle' => 'primaire', 'year_number' => 3, 'sort_order' => 3],
            ['name' => 'الرابعة ابتدائي', 'cycle' => 'primaire', 'year_number' => 4, 'sort_order' => 4],
            ['name' => 'الخامسة ابتدائي', 'cycle' => 'primaire', 'year_number' => 5, 'sort_order' => 5],
        ];

        // CEM: years 1–4
        $cemLevelDefs = [
            ['name' => 'الأولى متوسط',    'cycle' => 'cem', 'year_number' => 1, 'sort_order' => 6],
            ['name' => 'الثانية متوسط',   'cycle' => 'cem', 'year_number' => 2, 'sort_order' => 7],
            ['name' => 'الثالثة متوسط',   'cycle' => 'cem', 'year_number' => 3, 'sort_order' => 8],
            ['name' => 'الرابعة متوسط',   'cycle' => 'cem', 'year_number' => 4, 'sort_order' => 9],
        ];

        // Subject coefficients per level type
        $primaryCoeffs = [
            'AR' => 3, 'MATH' => 3, 'FR' => 2, 'EN' => 2, 'ISL' => 2,
            'CIV' => 1, 'HISGEO' => 2, 'NS' => 2, 'SP' => 1, 'ART' => 1, 'MUS' => 1, 'SCI' => 2,
        ];
        $cemCoeffs = [
            'AR' => 3, 'MATH' => 4, 'FR' => 3, 'EN' => 2, 'ISL' => 1,
            'CIV' => 1, 'HISGEO' => 2, 'NS' => 2, 'PHYS' => 3, 'INFO' => 1, 'SP' => 1,
        ];

        $primaryLevels = [];
        $cemLevels     = [];

        foreach ($primaryLevelDefs as $def) {
            $level = Level::create(array_merge($def, ['is_active' => true]));
            foreach ($primaryCoeffs as $code => $coeff) {
                if (isset($allSubjects[$code])) {
                    LevelSubject::create([
                        'level_id'                 => $level->id,
                        'subject_id'               => $allSubjects[$code]->id,
                        'coefficient'              => $coeff,
                        'weekly_sessions_required' => ($code === 'AR' || $code === 'MATH') ? 5 : (($code === 'FR' || $code === 'EN') ? 3 : 2),
                    ]);
                }
            }
            $primaryLevels[] = $level;
        }

        foreach ($cemLevelDefs as $def) {
            $level = Level::create(array_merge($def, ['is_active' => true]));
            foreach ($cemCoeffs as $code => $coeff) {
                if (isset($allSubjects[$code])) {
                    LevelSubject::create([
                        'level_id'                 => $level->id,
                        'subject_id'               => $allSubjects[$code]->id,
                        'coefficient'              => $coeff,
                        'weekly_sessions_required' => ($code === 'AR' || $code === 'MATH') ? 5 : (($code === 'FR' || $code === 'PHYS') ? 4 : (($code === 'EN' || $code === 'HISGEO' || $code === 'NS') ? 3 : 2)),
                    ]);
                }
            }
            $cemLevels[] = $level;
        }

        return [$primaryLevels, $cemLevels];
    }

    // ──────────────────────────────────────────────
    //  4. Supervisors
    // ──────────────────────────────────────────────
    private function seedSupervisors(array $cemLevels): array
    {
        $this->command->info('👁 Creating supervisors…');

        // 1 supervisor for ALL primary
        $n = $this->randomMaleName();
        $primarySupervisor = $this->createSupervisor('sup_primary', $n['first'], $n['last']);

        // 1 supervisor per CEM level (4 supervisors)
        $cemSupervisors = [];
        $cemLevelNames  = ['الأولى م', 'الثانية م', 'الثالثة م', 'الرابعة م'];
        foreach ($cemLevels as $idx => $level) {
            $sn = $this->randomMaleName();
            $cemSupervisors[$level->id] = $this->createSupervisor(
                'sup_cem' . ($idx + 1), $sn['first'], $sn['last']
            );
        }

        return [$primarySupervisor, $cemSupervisors];
    }

    private function createSupervisor(string $username, string $first, string $last): Supervisor
    {
        $user = $this->makeUser($username, 'supervisor');
        return Supervisor::create([
            'user_id'    => $user->id,
            'first_name' => $first,
            'last_name'  => $last,
            'phone'      => $this->algPhone(),
            'hire_date'  => '2020-09-01',
            'status'     => 'active',
        ]);
    }

    // ──────────────────────────────────────────────
    //  5. Teachers
    // ──────────────────────────────────────────────
    private function seedTeachers(array $primarySubjects, array $cemSubjects): array
    {
        $this->command->info('👩‍🏫 Creating teachers…');

        /*
         * PRIMARY rules:
         *  – Each primary class has ONE main teacher who teaches ALL subjects
         *    EXCEPT French (FR) and English (EN).
         *  – French and English each have ONE dedicated teacher across ALL
         *    primary classes (they cover all 10 primary classes).
         *
         * We need: 10 class teachers + 1 FR teacher + 1 EN teacher = 12 primary teachers
         * But to add variety we create 2 FR + 2 EN teachers (each covers 5 classes).
         */

        $primaryTeachers = []; // indexed by slot: 0–9 = class teachers, 'fr1','fr2','en1','en2'

        // 10 class teachers (teach all except FR and EN)
        for ($i = 0; $i < 10; $i++) {
            $n = $this->randomMaleName();
            $teacher = $this->createTeacher(
                $this->nextUsername('teacher'),
                $n['first'], $n['last'],
                'معلم ابتدائي',
                array_keys(array_filter($primarySubjects, fn($s) => !in_array($s->code, ['FR', 'EN'])))
            );
            $primaryTeachers[$i] = $teacher;
        }

        // 2 French teachers (split across 10 primary classes)
        foreach (['fr1', 'fr2'] as $slot) {
            $n = $this->randomFemaleName();
            $teacher = $this->createTeacher(
                $this->nextUsername('teacher'),
                $n['first'], $n['last'],
                'أستاذ اللغة الفرنسية',
                ['FR']
            );
            $primaryTeachers[$slot] = $teacher;
        }

        // 2 English teachers
        foreach (['en1', 'en2'] as $slot) {
            $n = $this->randomMaleName();
            $teacher = $this->createTeacher(
                $this->nextUsername('teacher'),
                $n['first'], $n['last'],
                'أستاذ اللغة الإنجليزية',
                ['EN']
            );
            $primaryTeachers[$slot] = $teacher;
        }

        /*
         * CEM rules:
         *  – Each subject is taught by 2 teachers (each covers 4 of the 8 CEM classes).
         *  – So we need 2 teachers × 11 CEM subjects = 22 CEM teachers.
         */
        $cemSubjectTeachers = []; // code => [teacher_a, teacher_b]

        foreach (array_keys($cemSubjects) as $code) {
            $subjectName = $cemSubjects[$code]->name;
            $teachers = [];
            for ($t = 0; $t < 2; $t++) {
                $n = rand(0, 1) ? $this->randomMaleName() : $this->randomFemaleName();
                $teachers[] = $this->createTeacher(
                    $this->nextUsername('teacher'),
                    $n['first'], $n['last'],
                    "أستاذ {$subjectName}",
                    [$code]
                );
            }
            $cemSubjectTeachers[$code] = $teachers;
        }

        return [$primaryTeachers, $cemSubjectTeachers];
    }

    /**
     * @param string[] $subjectCodes
     */
    private function createTeacher(
        string $username, string $first, string $last,
        string $specialization, array $subjectCodes
    ): Teacher {
        $user = $this->makeUser($username, 'teacher');
        $teacher = Teacher::create([
            'user_id'        => $user->id,
            'first_name'     => $first,
            'last_name'      => $last,
            'specialization' => $specialization,
            'hire_date'      => Carbon::createFromDate(rand(2010, 2022), rand(1, 12), 1)->toDateString(),
            'salary'         => rand(4800, 8000),
            'contract_type'  => rand(0, 1) ? 'permanent' : 'part_time',
            'weekly_hours'   => 18,
        ]);

        foreach ($subjectCodes as $code) {
            $subject = Subject::where('code', $code)->first();
            if ($subject) {
                TeacherSubject::create([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                ]);
            }
        }

        return $teacher;
    }

    // ──────────────────────────────────────────────
    //  6. Classes
    // ──────────────────────────────────────────────
    private function seedClasses(
        array $primaryLevels, array $cemLevels,
        array $primaryTeachers, array $cemSubjectTeachers,
        Supervisor $primarySupervisor, array $cemSupervisors
    ): array {
        $this->command->info('🏛 Creating classes…');

        $primaryClasses = [];
        $cemClasses     = [];

        // 10 primary classes  → distribute 2 per level (5 levels × 2 = 10)
        $arabicLetters = ['أ', 'ب', 'ج', 'د', 'هـ'];
        $classIdx = 0;
        foreach ($primaryLevels as $levelIdx => $level) {
            for ($section = 0; $section < 2; $section++) {
                $letter = $arabicLetters[$section];
                $teacher = $primaryTeachers[$classIdx]; // the class teacher

                $class = SchoolClass::create([
                    'name'           => $level->name . ' ' . $letter,
                    'level'          => $level->name,
                    'level_id'       => $level->id,
                    'academic_year'  => '2025-2026',
                    'capacity'       => 30,
                    'main_teacher_id'=> $teacher->id,
                    'supervisor_id'  => $primarySupervisor->id,
                    'is_active'      => true,
                ]);
                $primaryClasses[$classIdx] = $class;
                $classIdx++;
            }
        }

        // 8 CEM classes → 2 per CEM level (4 levels × 2 = 8)
        $cemIdx = 0;
        foreach ($cemLevels as $cemLevelIdx => $level) {
            $supervisor = $cemSupervisors[$level->id];
            for ($section = 0; $section < 2; $section++) {
                $letter = $arabicLetters[$section];

                // The main teacher for a CEM class is the Arabic teacher
                // for that class's teacher slot (teacher_a or teacher_b).
                $arTeachers = $cemSubjectTeachers['AR'];
                $mainTeacher = $arTeachers[$section % 2];

                $class = SchoolClass::create([
                    'name'           => $level->name . ' ' . $letter,
                    'level'          => $level->name,
                    'level_id'       => $level->id,
                    'academic_year'  => '2025-2026',
                    'capacity'       => 35,
                    'main_teacher_id'=> $mainTeacher->id,
                    'supervisor_id'  => $supervisor->id,
                    'is_active'      => true,
                ]);
                $cemClasses[$cemIdx] = $class;
                $cemIdx++;
            }
        }

        return [$primaryClasses, $cemClasses];
    }

    // ──────────────────────────────────────────────
    //  7. Class–Subject–Teacher assignments
    // ──────────────────────────────────────────────
    private function assignClassSubjectTeachers(
        array $primaryClasses, array $cemClasses,
        array $primaryTeachers, array $cemSubjectTeachers,
        array $primarySubjects, array $cemSubjects
    ): void {
        $this->command->info('🔗 Assigning class–subject–teacher links…');

        // PRIMARY
        foreach ($primaryClasses as $idx => $class) {
            $classTeacher = $primaryTeachers[$idx];
            $frTeacher    = $primaryTeachers[$idx < 5 ? 'fr1' : 'fr2'];
            $enTeacher    = $primaryTeachers[$idx < 5 ? 'en1' : 'en2'];

            foreach ($primarySubjects as $code => $subject) {
                if ($code === 'FR') {
                    $teacher = $frTeacher;
                } elseif ($code === 'EN') {
                    $teacher = $enTeacher;
                } else {
                    $teacher = $classTeacher;
                }

                $coeff = LevelSubject::where('level_id', $class->level_id)
                    ->where('subject_id', $subject->id)
                    ->value('coefficient') ?? 1;

                ClassSubjectTeacher::create([
                    'class_id'     => $class->id,
                    'subject_id'   => $subject->id,
                    'teacher_id'   => $teacher->id,
                    'academic_year'=> '2025-2026',
                    'coefficient'  => $coeff,
                ]);
            }
        }

        // CEM  – each subject: teacher_a covers classes 0,1,2,3 ; teacher_b covers 4,5,6,7
        foreach ($cemClasses as $idx => $class) {
            foreach ($cemSubjects as $code => $subject) {
                $teacherSlot = ($idx < 4) ? 0 : 1;
                $teacher = $cemSubjectTeachers[$code][$teacherSlot];

                $coeff = LevelSubject::where('level_id', $class->level_id)
                    ->where('subject_id', $subject->id)
                    ->value('coefficient') ?? 1;

                ClassSubjectTeacher::create([
                    'class_id'     => $class->id,
                    'subject_id'   => $subject->id,
                    'teacher_id'   => $teacher->id,
                    'academic_year'=> '2025-2026',
                    'coefficient'  => $coeff,
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────
    //  8. Students & Parents
    // ──────────────────────────────────────────────
    private function seedStudentsAndParents(array $primaryClasses, array $cemClasses): void
    {
        $this->command->info('👧👦 Creating students and parents…');

        $allClasses = array_merge(array_values($primaryClasses), array_values($cemClasses));

        foreach ($allClasses as $class) {
            $count = rand(15, 20);
            for ($i = 0; $i < $count; $i++) {
                $gender   = rand(0, 1) ? 'male' : 'female';
                $name     = ($gender === 'male') ? $this->randomMaleName() : $this->randomFemaleName();
                $lastName = $name['last'];

                // Create a dedicated parent for each student
                // (some families may have siblings but for simplicity 1 parent per student
                // as the request says "link each student to a parent")
                $parentName = $this->randomMaleName(); // father
                $parent = ParentModel::create([
                    'first_name' => $parentName['first'],
                    'last_name'  => $lastName,
                    'phone'      => $this->algPhone(),
                    'email'      => 'parent' . ($this->studentCodeCounter) . '@mail.dz',
                    'cin'        => 'C' . str_pad((string)rand(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                    'profession' => $this->professions[array_rand($this->professions)],
                ]);

                // Birth date: primary ~6-12 yo, CEM ~11-16 yo
                $isPrimary = ($class->levelProfile->cycle ?? '') === 'primaire';
                $minAge = $isPrimary ? 6  : 11;
                $maxAge = $isPrimary ? 12 : 16;
                $birthYear = now()->year - rand($minAge, $maxAge);
                $birthDate = Carbon::createFromDate($birthYear, rand(1, 12), rand(1, 28))->toDateString();

                Student::create([
                    'first_name'      => $name['first'],
                    'last_name'       => $lastName,
                    'code'            => 'STU' . $this->studentCodeCounter++,
                    'birth_date'      => $birthDate,
                    'gender'          => $gender,
                    'class_id'        => $class->id,
                    'parent_id'       => $parent->id,
                    'enrollment_date' => '2025-09-01',
                    'is_active'       => true,
                ]);
            }
        }
    }
}
