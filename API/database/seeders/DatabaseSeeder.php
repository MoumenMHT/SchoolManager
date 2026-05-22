<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Level;
use App\Models\LevelSubject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

        if (User::where('username', 'admin')->exists()) {
            $this->command->info('Seed baseline already exists, skipping duplicate seeding.');
            $this->call(ExamsAndGradesSeeder::class);
            $this->call(FinancesSeeder::class);
            return;
        }

        // Load Excel data
        require __DIR__ . '/excel_data_snippets.php';

        // 1. Core Users (Admin, Staff, Directors)
        $this->command->info('Creating core users...');
        \App\Models\User::create([
            'username' => 'admin',
            'email' => 'admin@schoolmanager.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = ['secretariat', 'accountant'];
        foreach ($staff as $s) {
            \App\Models\User::create([
                'username' => $s,
                'email' => "$s@schoolmanager.com",
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => $s,
                'is_active' => true,
            ]);
        }

        $directors = ['primary', 'cem', 'lycee'];
        foreach ($directors as $d) {
            \App\Models\User::create([
                'username' => "director_$d",
                'email' => "$d@schoolmanager.com",
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => "{$d}_director",
                'is_active' => true,
            ]);
        }

        // 2. Subjects
        $this->command->info('Creating subjects...');
        $subjects = [
            ['name' => 'اللغة العربية', 'code' => 'AR'],
            ['name' => 'الرياضيات', 'code' => 'MATH'],
            ['name' => 'ع الفيزيائية والتكنولوجيا', 'code' => 'PHYS'],
            ['name' => 'التربية الإسلامية', 'code' => 'ISL'],
            ['name' => 'التربية المدنية', 'code' => 'CIV'],
            ['name' => 'اللغة الفرنسية', 'code' => 'FR'],
            ['name' => 'اللغة الإنجليزية', 'code' => 'EN'],
            ['name' => 'التاريخ والجغرافيا', 'code' => 'HISGEO'],
            ['name' => 'ع الطبيعة والحياة', 'code' => 'NS'],
            ['name' => 'المعلوماتية', 'code' => 'INFO'],
            ['name' => 'ت البدنية والرياضة', 'code' => 'SP'],
            ['name' => 'التربية التشكيلية', 'code' => 'ART'],
            ['name' => 'التربية الموسيقية', 'code' => 'MUS'],
            ['name' => 'ت العلمية والتكنولوجيا', 'code' => 'SCI'],
        ];

        $createdSubjects = [];
        foreach ($subjects as $s) {
            $createdSubjects[] = \App\Models\Subject::create($s);
        }

        // 3. Cycles and Levels
        $this->command->info('Creating levels...');
        $levels = [
            ['name' => 'الرابعة ا', 'cycle' => 'primaire', 'year_number' => 4, 'sort_order' => 4],
            ['name' => 'الثانية م', 'cycle' => 'cem', 'year_number' => 2, 'sort_order' => 7],
        ];

        $createdLevelsByName = [];
        foreach ($levels as $l) {
            $level = Level::create(array_merge($l, ['is_active' => true]));
            $createdLevelsByName[$level->name] = $level;
        }

        // 4. Level-Subject Links
        $this->command->info('Linking subjects to levels...');
        foreach ($createdSubjects as $s) {
            foreach ($createdLevelsByName as $l) {
                LevelSubject::create([
                    'level_id' => $l->id,
                    'subject_id' => $s->id,
                    'coefficient' => 1,
                    'weekly_sessions_required' => 2,
                ]);
            }
        }

        // 5. Teachers from Excel
        $this->command->info('Creating Excel teachers...');
        $teachers = [];
        $subjectsByName = [];
        foreach ($createdSubjects as $s) { $subjectsByName[$s->name] = $s; }

        foreach ($excel_teachers as $index => $tData) {
            $user = \App\Models\User::create([
                'username' => 'teacher' . ($index + 1),
                'email' => 'teacher' . ($index + 1) . '@schoolmanager.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'teacher',
                'is_active' => true,
            ]);

            $nameParts = explode(' ', $tData['full_name'], 2);
            $teacher = \App\Models\Teacher::create([
                'user_id' => $user->id,
                'first_name' => $nameParts[1] ?? '',
                'last_name' => $nameParts[0],
                'specialization' => $tData['subject'],
                'hire_date' => now(),
                'salary' => 5000,
                'contract_type' => 'permanent',
                'weekly_hours' => 22,
            ]);

            $teachers[] = $teacher;
            $subject = $subjectsByName[$tData['subject']] ?? null;
            if ($subject) {
                \App\Models\TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
            }
        }

        // 6. Supervisors (5)
        $this->command->info('Creating supervisors...');
        $supervisors = [];
        for ($i = 0; $i < 5; $i++) {
            $user = \App\Models\User::create([
                'username' => 'supervisor' . ($i + 1),
                'email' => 'supervisor' . ($i + 1) . '@schoolmanager.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'supervisor',
                'is_active' => true,
            ]);

            $supervisors[] = \App\Models\Supervisor::create([
                'user_id' => $user->id,
                'first_name' => 'Supervisor',
                'last_name' => ($i + 1),
                'hire_date' => now(),
            ]);
        }

        // 7. Classes
        $this->command->info('Creating classes...');
        $classesByName = [];
        $uniqueClasses = array_unique(array_column($excel_students, 'class'));
        foreach ($uniqueClasses as $idx => $cName) {
            $levelName = str_contains($cName, 'الرابعة ا') ? 'الرابعة ا' : (str_contains($cName, 'الثانية م') ? 'الثانية م' : '');
            if (!$levelName) continue;

            $class = \App\Models\SchoolClass::create([
                'name' => $cName,
                'level' => $levelName,
                'level_id' => $createdLevelsByName[$levelName]->id,
                'academic_year' => '2025-2026',
                'main_teacher_id' => $teachers[array_rand($teachers)]->id,
                'supervisor_id' => $supervisors[$idx % count($supervisors)]->id,
                'is_active' => true,
            ]);
            $classesByName[$cName] = $class;
        }

        // 8. Students & Parents (Excel)
        $this->command->info('Creating students and parents...');
        $parentsByFather = [];

        foreach ($excel_students as $sData) {
            if (!isset($parentsByFather[$sData['father_name']])) {
                $parentsByFather[$sData['father_name']] = \App\Models\ParentModel::create([
                    'first_name' => $sData['father_name'],
                    'last_name' => $sData['last_name'],
                    'phone' => '+213' . fake()->numerify('6########'),
                    'email' => fake()->unique()->email(),
                ]);
            }
            $parent = $parentsByFather[$sData['father_name']];
            $class = $classesByName[$sData['class']] ?? null;
            if (!$class) continue;

            \App\Models\Student::create([
                'first_name' => $sData['first_name'],
                'last_name' => $sData['last_name'],
                'code' => 'STU' . $sData['reg_no'],
                'birth_date' => $sData['birth_date'],
                'class_id' => $class->id,
                'parent_id' => $parent->id,
                'enrollment_date' => '2025-09-01',
                'is_active' => true,
            ]);
        }

        $this->command->info('✅ Clean seed complete!');

        // Seed exams, exercises, and grades for existing students
        $this->call(ExamsAndGradesSeeder::class);

        // Seed finances (Contracts, Bills, Payments)
        $this->call(FinancesSeeder::class);
    }
}
