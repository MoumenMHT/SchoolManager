<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassSubjectTeacher;
use App\Models\Contract;
use App\Models\Exam;
use App\Models\Fee;
use App\Models\Grade;
use App\Models\ParentModel;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLogicAndValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test contract creation rejects percentage discounts > 100%
     */
    public function test_contract_rejects_percentage_discount_greater_than_100(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $parent = ParentModel::factory()->create();
        $student = Student::factory()->create(['parent_id' => $parent->id]);
        $fee = Fee::factory()->create(['base_amount' => 1000]);
        $ay = AcademicYear::create([
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => true,
            'tenant_id' => $admin->tenant_id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/contracts', [
                'parent_id' => $parent->id,
                'academic_year_id' => $ay->id,
                'start_date' => '2025-09-01',
                'end_date' => '2026-06-30',
                'discount_type' => 'percentage',
                'discount_value' => 150, // Invalid > 100%
                'student_fees' => [
                    [
                        'student_id' => $student->id,
                        'fee_ids' => [$fee->id]
                    ]
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount_value']);
    }

    /**
     * Test payment creation rejects $0.00 amount
     */
    public function test_payment_rejects_zero_or_negative_amounts(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $token = $accountant->createToken('test-token')->plainTextToken;

        $contract = Contract::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'amount' => 0, // Zero is invalid
                'payment_type' => 'cash',
                'paid_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test GradeController@show endpoint returns grade without PHP error
     */
    public function test_grade_show_endpoint_returns_grade_for_authorized_user(): void
    {
        $teacherUser = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

        $class = SchoolClass::factory()->create();
        $subject = Subject::factory()->create();
        
        ClassSubjectTeacher::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 1,
            'tenant_id' => $teacherUser->tenant_id,
        ]);

        $student = Student::factory()->create(['class_id' => $class->id]);
        $exam = Exam::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'tenant_id' => $teacherUser->tenant_id,
        ]);
        $grade = Grade::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'grade' => 15,
            'tenant_id' => $teacherUser->tenant_id,
        ]);

        $token = $teacherUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/grades/' . $grade->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $grade->id,
                    'grade' => 15,
                ]
            ]);
    }

    /**
     * Test schedule conflict checker handles lowercase day string inputs
     */
    public function test_schedule_conflict_check_handles_lowercase_day_names(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $teacher = Teacher::factory()->create();
        $class = SchoolClass::factory()->create();
        $subject = Subject::factory()->create();
        $ay = AcademicYear::factory()->create(['is_current' => true]);

        $cst = ClassSubjectTeacher::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $ay->id,
            'coefficient' => 1,
            'tenant_id' => $admin->tenant_id,
        ]);

        // Existing schedule on Monday
        Schedule::create([
            'class_subject_teacher_id' => $cst->id,
            'day' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'tenant_id' => $admin->tenant_id,
        ]);

        // Query checkConflicts using lowercase 'monday'
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/schedules/check-conflicts', [
                'class_subject_teacher_id' => $cst->id,
                'day' => 'monday',
                'start_time' => '08:30',
                'end_time' => '09:30',
            ]);

        $response->assertStatus(200);
        $conflicts = $response->json('conflicts');
        $this->assertNotEmpty($conflicts);
    }

    /**
     * Test attendance index returns paginated dataset
     */
    public function test_attendance_index_returns_paginated_dataset(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token = $teacher->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        Attendance::create([
            'student_id' => $student->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'present',
            'tenant_id' => $teacher->tenant_id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/attendances');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data',
                ]
            ]);
    }

    /**
     * Test supervisor cannot access class schedule today for unassigned class
     */
    public function test_supervisor_cannot_access_unassigned_class_schedule(): void
    {
        $user = User::factory()->create(['role' => 'supervisor']);
        $supervisor = Supervisor::create([
            'user_id' => $user->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'status' => 'active',
            'tenant_id' => $user->tenant_id,
        ]);

        $unassignedClass = SchoolClass::factory()->create([
            'supervisor_id' => null,
            'tenant_id' => $user->tenant_id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/supervisor/classes/' . $unassignedClass->id . '/schedule-today');

        $response->assertStatus(404);
    }
}
