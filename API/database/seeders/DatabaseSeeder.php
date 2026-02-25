<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

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
            ['name' => 'Mathematics', 'code' => 'MATH', 'description' => 'Mathematics curriculum'],
            ['name' => 'Physics', 'code' => 'PHYS', 'description' => 'Physics curriculum'],
            ['name' => 'Chemistry', 'code' => 'CHEM', 'description' => 'Chemistry curriculum'],
            ['name' => 'French', 'code' => 'FR', 'description' => 'French language'],
            ['name' => 'Arabic', 'code' => 'AR', 'description' => 'Arabic language'],
            ['name' => 'English', 'code' => 'EN', 'description' => 'English language'],
            ['name' => 'History', 'code' => 'HIST', 'description' => 'History curriculum'],
            ['name' => 'Geography', 'code' => 'GEO', 'description' => 'Geography curriculum'],
            ['name' => 'Biology', 'code' => 'BIO', 'description' => 'Biology curriculum'],
            ['name' => 'Physical Education', 'code' => 'PE', 'description' => 'Physical Education'],
        ];

        $createdSubjects = [];
        foreach ($subjects as $subject) {
            $createdSubjects[] = \App\Models\Subject::create($subject);
        }

        // Create subject coefficients for different levels
        $this->command->info('Creating subject coefficients...');
        $levels = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9'];
        foreach ($createdSubjects as $subject) {
            foreach ($levels as $level) {
                \App\Models\SubjectCoefficient::create([
                    'subject_id' => $subject->id,
                    'class_level' => $level,
                    'coefficient' => rand(1, 4),
                ]);
            }
        }

        // Create teachers (20 teachers)
        $this->command->info('Creating 20 teachers...');
        $teachers = [];
        $specializations = ['Mathematics', 'Physics', 'Chemistry', 'French', 'Arabic', 'English', 'History', 'Geography', 'Biology', 'Physical Education'];
        
        for ($i = 0; $i < 20; $i++) {
            $teacherUser = \App\Models\User::create([
                'username' => fake()->name(),
                'email' => 'teacher' . ($i + 1) . '@schoolmanager.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'teacher',
                'phone' => '+212' . fake()->numerify('6########'),
                'address' => fake()->address(),
                'is_active' => true,
            ]);

            $teacher = \App\Models\Teacher::create([
                'user_id' => $teacherUser->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'cin' => fake()->unique()->regexify('[A-Z]{2}[0-9]{6}'),
                'birth_date' => fake()->date('Y-m-d', '-30 years'),
                'specialization' => $specializations[array_rand($specializations)],
                'hire_date' => fake()->date('Y-m-d', '-5 years'),
                'salary' => fake()->randomFloat(2, 3000, 8000),
            ]);

            $teachers[] = $teacher;

            // Assign subjects to teachers
            $randomSubjects = fake()->randomElements($createdSubjects, rand(1, 3));
            foreach ($randomSubjects as $subject) {
                \App\Models\TeacherSubject::create([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                ]);
            }
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
                    'phone' => '+212' . fake()->numerify('6########'),
                    'address' => fake()->address(),
                    'is_active' => true,
                ]);
            }

            $parent = \App\Models\ParentModel::create([
                'user_id' => $parentUser?->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'cin' => fake()->unique()->regexify('[A-Z]{2}[0-9]{6}'),
                'phone' => $hasAccount ? null : '+212' . fake()->numerify('6########'),
                'email' => $hasAccount ? null : fake()->unique()->email(),
                'profession' => fake()->randomElement(['Engineer', 'Doctor', 'Teacher', 'Lawyer', 'Businessman', 'Accountant']),
            ]);

            $parents[] = $parent;
        }

        // Create classes (8 classes)
        $this->command->info('Creating 8 classes...');
        $classes = [];
        $classNames = ['A', 'B', 'C', 'D'];
        
        foreach ($levels as $level) {
            foreach (array_slice($classNames, 0, 2) as $name) {
                $class = \App\Models\SchoolClass::create([
                    'name' => $level . ' ' . $name,
                    'level' => $level,
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

        // Create class-subject-teacher assignments
        $this->command->info('Creating class assignments...');
        $assignments = [];
        
        foreach ($classes as $class) {
            // Assign 5-7 subjects per class
            $classSubjects = fake()->randomElements($createdSubjects, rand(5, 7));
            
            foreach ($classSubjects as $subject) {
                // Find a teacher who teaches this subject
                $teacherSubjects = \App\Models\TeacherSubject::where('subject_id', $subject->id)->get();
                if ($teacherSubjects->isNotEmpty()) {
                    $teacherSubject = $teacherSubjects->random();
                    
                    $assignment = \App\Models\ClassSubjectTeacher::create([
                        'class_id' => $class->id,
                        'subject_id' => $subject->id,
                        'teacher_id' => $teacherSubject->teacher_id,
                        'academic_year' => '2025-2026',
                    ]);
                    $assignments[] = $assignment;
                }
            }
        }

        // Create schedules for assignments
        $this->command->info('Creating schedules...');
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $rooms = ['Room 101', 'Room 102', 'Room 103', 'Room 201', 'Room 202', 'Room 203', 'Lab 1', 'Lab 2'];
        
        foreach ($assignments as $assignment) {
            // Create 2 schedule slots per assignment
            for ($i = 0; $i < 2; $i++) {
                $startHour = rand(8, 14);
                \App\Models\Schedule::create([
                    'class_subject_teacher_id' => $assignment->id,
                    'day' => $days[array_rand($days)],
                    'start_time' => sprintf('%02d:00', $startHour),
                    'end_time' => sprintf('%02d:00', $startHour + 2),
                    'room' => $rooms[array_rand($rooms)],
                ]);
            }
        }

        // Create attendance records (300 records)
        $this->command->info('Creating 300 attendance records...');
        foreach ($students as $student) {
            for ($i = 0; $i < 2; $i++) {
                $classAssignment = $assignments[array_rand($assignments)];
                
                \App\Models\Attendance::create([
                    'student_id' => $student->id,
                    'subject_id' => $classAssignment->subject_id,
                    'teacher_id' => $classAssignment->teacher_id,
                    'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
                    'status' => fake()->randomElement(['present', 'absent', 'late', 'excused']),
                    'time' => fake()->time('H:i'),
                    'reason' => fake()->optional(0.3)->sentence(),
                ]);
            }
        }

        // Create grades (450 grades)
        $this->command->info('Creating 450 grade records...');
        $examTypes = ['Quiz', 'Midterm', 'Final', 'Homework', 'Project'];
        $semesters = ['Semester 1', 'Semester 2'];
        
        foreach ($students as $student) {
            for ($i = 0; $i < 3; $i++) {
                $classAssignment = $assignments[array_rand($assignments)];
                
                \App\Models\Grade::create([
                    'student_id' => $student->id,
                    'subject_id' => $classAssignment->subject_id,
                    'teacher_id' => $classAssignment->teacher_id,
                    'exam_type' => $examTypes[array_rand($examTypes)],
                    'grade' => fake()->randomFloat(2, 0, 20),
                    'max_grade' => 20,
                    'semester' => $semesters[array_rand($semesters)],
                    'academic_year' => '2025-2026',
                    'comment' => fake()->optional(0.3)->sentence(),
                ]);
            }
        }

        // Create payments (100 payment records)
        $this->command->info('Creating 100 payment records...');
        $paymentTypes = ['Tuition Fee', 'Registration Fee', 'Books', 'Activities', 'Transport', 'Cafeteria'];
        $months = ['September', 'October', 'November', 'December', 'January', 'February', 'March', 'April', 'May', 'June'];
        
        foreach ($students as $student) {
            if (rand(1, 2) == 1) {
                $dueDate = fake()->dateTimeBetween('-6 months', '+1 month');
                $status = fake()->randomElement(['paid', 'pending', 'late']);
                $paidDate = null;
                
                if ($status === 'paid' && $dueDate < new \DateTime()) {
                    $paidDate = fake()->dateTimeBetween($dueDate, 'now')->format('Y-m-d');
                }
                
                \App\Models\Payment::create([
                    'student_id' => $student->id,
                    'amount' => fake()->randomFloat(2, 500, 5000),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'paid_date' => $paidDate,
                    'status' => $status,
                    'payment_type' => $paymentTypes[array_rand($paymentTypes)],
                    'academic_year' => '2025-2026',
                    'month' => $months[array_rand($months)],
                    'notes' => fake()->optional(0.3)->sentence(),
                ]);
            }
        }

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('📊 Summary:');
        $this->command->info('   • 1 Admin user');
        $this->command->info('   • 20 Teachers');
        $this->command->info('   • 50 Parents (25 with accounts)');
        $this->command->info('   • 150 Students');
        $this->command->info('   • 10 Subjects');
        $this->command->info('   • 8 Classes');
        $this->command->info('   • 300+ Attendance records');
        $this->command->info('   • 450+ Grade records');
        $this->command->info('   • 100+ Payment records');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔐 Login credentials:');
        $this->command->info('   Admin: admin@schoolmanager.com / password123');
        $this->command->info('   Teachers: teacher1-20@schoolmanager.com / password123');
        $this->command->info('   Parents: parent1-25@schoolmanager.com / password123');
    }
}
