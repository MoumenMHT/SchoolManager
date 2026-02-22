<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test teacher can create grade
     */
    public function test_teacher_can_create_grade(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        $subject = Subject::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'exam_type' => 'midterm',
                'grade' => 15.5,
                'max_grade' => 20,
                'semester' => '1',
                'academic_year' => '2025-2026',
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'grade' => 15.5,
        ]);
    }

    /**
     * Test teacher can bulk create grades
     */
    public function test_teacher_can_bulk_create_grades(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $students = Student::factory()->count(3)->create();
        $subject = Subject::factory()->create();

        $grades = $students->map(function ($student) use ($teacher, $subject) {
            return [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'exam_type' => 'midterm',
                'grade' => 15,
                'max_grade' => 20,
                'semester' => '1',
                'academic_year' => '2025-2026',
            ];
        })->toArray();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades/bulk', [
                'grades' => $grades,
            ]);

        $response->assertStatus(201);
        
        $this->assertEquals(3, Grade::count());
    }

    /**
     * Test can get student grades
     */
    public function test_can_get_student_grades(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        Grade::factory()->count(5)->create(['student_id' => $student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $student->id . '/grades');

        $response->assertStatus(200);
    }

    /**
     * Test can get student report card
     */
    public function test_can_get_student_report_card(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        Grade::factory()->count(5)->create(['student_id' => $student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $student->id . '/report-card?semester=1&academic_year=2025-2026');

        $response->assertStatus(200);
    }

    /**
     * Test can get class grades
     */
    public function test_can_get_class_grades(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        Grade::factory()->count(5)->create(['student_id' => $student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes/' . $student->class_id . '/grades');

        $response->assertStatus(200);
    }

    /**
     * Test can get class ranking
     */
    public function test_can_get_class_ranking(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        Grade::factory()->count(5)->create(['student_id' => $student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes/' . $student->class_id . '/ranking?semester=1&academic_year=2025-2026');

        $response->assertStatus(200);
    }

    /**
     * Test parent cannot create grades
     */
    public function test_parent_cannot_create_grades(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $token = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [
                'student_id' => 1,
                'grade' => 15,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test grade validation
     */
    public function test_grade_validation(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [
                'grade' => 25, // Invalid: exceeds max
            ]);

        $response->assertStatus(422);
    }
}
