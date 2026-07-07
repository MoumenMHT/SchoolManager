<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test teacher can mark attendance
     */
    public function test_teacher_can_mark_attendance(): void
    {
        $user    = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $token   = $user->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();
        $subject = Subject::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendances', [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'date'       => now()->format('Y-m-d'),
                'status'     => 'present',
                // 'time' is set automatically by the controller; do not send it
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'status'     => 'present',
        ]);
    }

    /**
     * Test can get student attendance
     */
    public function test_can_get_student_attendance(): void
    {
        $user  = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $student     = Student::factory()->create();
        $attendances = Attendance::factory()->count(10)->create(['student_id' => $student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $student->id . '/attendances');

        $response->assertStatus(200);
    }

    /**
     * Test can get class attendance
     */
    public function test_can_get_class_attendance(): void
    {
        $user  = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $student     = Student::factory()->create();
        $attendances = Attendance::factory()->count(10)->create(['student_id' => $student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes/' . $student->class_id . '/attendances');

        $response->assertStatus(200);
    }

    /**
     * Test can update attendance — controller only accepts status and reason
     */
    public function test_can_update_attendance(): void
    {
        $user  = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $attendance = Attendance::factory()->create(['status' => 'present']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/attendances/' . $attendance->id, [
                'status' => 'absent',
                'reason' => 'Sick leave',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendances', [
            'id'     => $attendance->id,
            'status' => 'absent',
        ]);
    }

    /**
     * Test attendance status validation
     */
    public function test_attendance_status_validation(): void
    {
        $user  = User::factory()->create(['role' => 'teacher']);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendances', [
                'student_id' => 1,
                'status'     => 'invalid_status',
            ]);

        $response->assertStatus(422);
    }
}
