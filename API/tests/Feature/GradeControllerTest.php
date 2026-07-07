<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test teacher can create grade (new exam-based architecture)
     */
    public function test_teacher_can_create_grade(): void
    {
        $user    = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $token   = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        $exam    = Exam::factory()->create(['teacher_id' => $teacher->id, 'max_grade' => 20]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [
                'student_id' => $student->id,
                'exam_id'    => $exam->id,
                'grade'      => 15.5,
                'comment'    => 'Good work',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'exam_id'    => $exam->id,
        ]);
    }

    /**
     * Test teacher can bulk create grades (new exam-based architecture)
     */
    public function test_teacher_can_bulk_create_grades(): void
    {
        $user    = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $token   = $user->createToken('test-token')->plainTextToken;

        $students = Student::factory()->count(3)->create();
        $exam     = Exam::factory()->create(['teacher_id' => $teacher->id, 'max_grade' => 20]);

        $grades = $students->map(function ($student) use ($exam) {
            return [
                'student_id' => $student->id,
                'exam_id'    => $exam->id,
                'grade'      => 15,
            ];
        })->toArray();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades/bulk', [
                'grades' => $grades,
            ]);

        $response->assertStatus(200);

        $this->assertEquals(3, Grade::count());
    }

    /**
     * Test can get student grades
     */
    public function test_can_get_student_grades(): void
    {
        $user  = User::factory()->create(['role' => 'teacher']);
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
        $user  = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        Grade::factory()->count(5)->create(['student_id' => $student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $student->id . '/report-card?semester=Trimester+1&academic_year=2025-2026');

        $response->assertStatus(200);
    }

    /**
     * Test can get class grades
     */
    public function test_can_get_class_grades(): void
    {
        $user  = User::factory()->create(['role' => 'teacher']);
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
        $user  = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes/' . $student->class_id . '/ranking?semester=Trimester+1&academic_year=2025-2026');

        $response->assertStatus(200);
    }

    /**
     * Test parent cannot create grades
     */
    public function test_parent_cannot_create_grades(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $token  = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [
                'student_id' => 1,
                'grade'      => 15,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test grade validation — missing required fields returns 422
     */
    public function test_grade_validation(): void
    {
        $user  = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [
                // Missing student_id and exam_id
                'grade' => 15,
            ]);

        $response->assertStatus(422);
    }
}
