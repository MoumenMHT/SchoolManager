<?php

namespace Tests\Feature\BusinessLogic;

use Tests\TestCase;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradeLogicTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $teacherUser1;
    protected $teacherUser2;
    protected $teacher1;
    protected $teacher2;
    protected $student;
    protected $class;
    protected $subject;
    protected $exam1; // Belongs to teacher1
    protected $exam2; // Belongs to teacher2

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacherUser1 = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->teacher1 = Teacher::factory()->create(['user_id' => $this->teacherUser1->id]);

        $this->teacherUser2 = User::factory()->create(['role' => 'teacher', 'is_active' => true]);
        $this->teacher2 = Teacher::factory()->create(['user_id' => $this->teacherUser2->id]);

        $this->class = SchoolClass::factory()->create();
        $this->subject = Subject::factory()->create();
        
        $this->student = Student::factory()->create(['class_id' => $this->class->id]);

        // Assign teacher1 to the class
        $this->teacher1->classes()->attach($this->class->id, ['subject_id' => $this->subject->id]);

        $this->exam1 = Exam::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'subject_id' => $this->subject->id,
            'max_grade' => 20
        ]);
        $this->exam1->classes()->attach($this->class->id);

        $this->exam2 = Exam::factory()->create([
            'teacher_id' => $this->teacher2->id,
            'subject_id' => $this->subject->id,
            'max_grade' => 20
        ]);
        // exam2 is not attached to this->class
    }

    public function test_prevent_grading_other_teacher_exam()
    {
        $this->actingAs($this->teacherUser1);

        // Teacher1 tries to grade exam2 which belongs to Teacher2
        $response = $this->postJson("/api/grades", [
            'student_id' => $this->student->id,
            'exam_id' => $this->exam2->id,
            'grade' => 15,
        ]);

        $response->assertStatus(403);
    }

    public function test_prevent_grading_student_not_in_exam_class()
    {
        $this->actingAs($this->teacherUser1);

        // Remove exam1 from class
        $this->exam1->classes()->detach($this->class->id);

        // Teacher1 tries to grade exam1 for student in a class not assigned to the exam
        $response = $this->postJson("/api/grades", [
            'student_id' => $this->student->id,
            'exam_id' => $this->exam1->id,
            'grade' => 15,
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', __('messages.exam_not_assigned_to_class', ['default' => 'This exam is not assigned to the student\'s class.']));
    }

    public function test_prevent_duplicate_grades_via_updateOrCreate()
    {
        $this->actingAs($this->teacherUser1);

        // Grade 1st time
        $response1 = $this->postJson("/api/grades", [
            'student_id' => $this->student->id,
            'exam_id' => $this->exam1->id,
            'grade' => 10,
        ]);
        $response1->assertStatus(201);
        
        $this->assertDatabaseCount('grades', 1);

        // Grade 2nd time for same student and exam
        $response2 = $this->postJson("/api/grades", [
            'student_id' => $this->student->id,
            'exam_id' => $this->exam1->id,
            'grade' => 15, // Update grade
        ]);
        $response2->assertStatus(201);

        $this->assertDatabaseCount('grades', 1);
        $this->assertEquals(15, Grade::first()->grade);
    }
}
