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
        // Create admin user
        \App\Models\User::create([
            'username' => 'Admin User',
            'email' => 'admin@schoolmanager.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'admin',
            'phone' => '+212600000000',
            'address' => 'Admin Address',
            'is_active' => true,
        ]);

        // Create sample teacher user
        $teacherUser = \App\Models\User::create([
            'username' => 'Teacher User',
            'email' => 'teacher@schoolmanager.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'teacher',
            'phone' => '+212600000001',
            'address' => 'Teacher Address',
            'is_active' => true,
        ]);

        // Create teacher profile
        \App\Models\Teacher::create([
            'user_id' => $teacherUser->id,
            'first_name' => 'Mohamed',
            'last_name' => 'Alami',
            'cin' => 'AB123456',
            'birth_date' => '1985-05-15',
            'specialization' => 'Mathematics',
            'hire_date' => '2020-09-01',
            'salary' => 5000.00,
        ]);

        // Create sample parent user
        $parentUser = \App\Models\User::create([
            'username' => 'Parent User',
            'email' => 'parent@schoolmanager.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'parent',
            'phone' => '+212600000002',
            'address' => 'Parent Address',
            'is_active' => true,
        ]);

        // Create parent profile (with account)
        $parent = \App\Models\ParentModel::create([
            'user_id' => $parentUser->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Bennani',
            'cin' => 'CD789012',
            'profession' => 'Engineer',
        ]);

        // Create parent without account
        $parentNoAccount = \App\Models\ParentModel::create([
            'user_id' => null,
            'first_name' => 'Fatima',
            'last_name' => 'Idrissi',
            'phone' => '+212600000003',
            'email' => 'fatima.idrissi@example.com',
            'cin' => 'EF345678',
            'profession' => 'Doctor',
        ]);

        // Create sample subjects
        $mathSubject = \App\Models\Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH01',
            'description' => 'Mathematics curriculum',
        ]);

        $frenchSubject = \App\Models\Subject::create([
            'name' => 'French',
            'code' => 'FR01',
            'description' => 'French language',
        ]);

        $arabicSubject = \App\Models\Subject::create([
            'name' => 'Arabic',
            'code' => 'AR01',
            'description' => 'Arabic language',
        ]);

        // Seed subject coefficients for different levels
        \App\Models\SubjectCoefficient::create([
            'subject_id' => $mathSubject->id,
            'class_level' => 'Grade 6',
            'coefficient' => 3,
        ]);

        \App\Models\SubjectCoefficient::create([
            'subject_id' => $frenchSubject->id,
            'class_level' => 'Grade 6',
            'coefficient' => 2,
        ]);

        \App\Models\SubjectCoefficient::create([
            'subject_id' => $arabicSubject->id,
            'class_level' => 'Grade 6',
            'coefficient' => 2,
        ]);

        // Seed teacher-subject relationships
        \App\Models\TeacherSubject::create([
            'teacher_id' => 1,
            'subject_id' => $mathSubject->id,
        ]);

        \App\Models\TeacherSubject::create([
            'teacher_id' => 1,
            'subject_id' => $frenchSubject->id,
        ]);

        // Create sample class
        $class = \App\Models\SchoolClass::create([
            'name' => '6ème A',
            'level' => 'Grade 6',
            'academic_year' => '2025-2026',
            'capacity' => 30,
            'main_teacher_id' => 1,
            'is_active' => true,
        ]);

        // Create sample student
        $student = \App\Models\Student::create([
            'first_name' => 'Youssef',
            'last_name' => 'Bennani',
            'code' => 'STU2025001',
            'birth_date' => '2013-03-20',
            'gender' => 'male',
            'class_id' => $class->id,
            'parent_id' => $parent->id,
            'enrollment_date' => '2025-09-01',
            'is_active' => true,
        ]);

        // Create student with parent who has no account
        $student2 = \App\Models\Student::create([
            'first_name' => 'Sara',
            'last_name' => 'Idrissi',
            'code' => 'STU2025002',
            'birth_date' => '2013-07-15',
            'gender' => 'female',
            'class_id' => $class->id,
            'parent_id' => $parentNoAccount->id,
            'enrollment_date' => '2025-09-01',
            'is_active' => true,
        ]);

        // Create teaching assignments (coefficient will auto-fill from subject_coefficients)
        $assignment1 = \App\Models\ClassSubjectTeacher::create([
            'class_id' => $class->id,
            'subject_id' => $mathSubject->id,
            'teacher_id' => 1,
            'academic_year' => '2025-2026',
            // coefficient will be auto-filled as 3 from subject_coefficients
        ]);

        $assignment2 = \App\Models\ClassSubjectTeacher::create([
            'class_id' => $class->id,
            'subject_id' => $frenchSubject->id,
            'teacher_id' => 1,
            'academic_year' => '2025-2026',
            // coefficient will be auto-filled as 2 from subject_coefficients
        ]);

        // Create schedules for assignments
        \App\Models\Schedule::create([
            'class_subject_teacher_id' => $assignment1->id,
            'day' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Room 101',
        ]);

        \App\Models\Schedule::create([
            'class_subject_teacher_id' => $assignment1->id,
            'day' => 'Wednesday',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'room' => 'Room 101',
        ]);

        \App\Models\Schedule::create([
            'class_subject_teacher_id' => $assignment2->id,
            'day' => 'Tuesday',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'room' => 'Room 102',
        ]);

        $this->command->info('✅ Sample data seeded successfully!');
        $this->command->info('Admin: admin@schoolmanager.com / password123');
        $this->command->info('Teacher: teacher@schoolmanager.com / password123');
        $this->command->info('Parent: parent@schoolmanager.com / password123');
    }
}
