<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Schedule;
use App\Models\ClassSubjectTeacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can create schedule
     */
    public function test_admin_can_create_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $assignment = ClassSubjectTeacher::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/schedules', [
                'class_subject_teacher_id' => $assignment->id,
                'day' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'room' => 'Room 101',
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('schedules', [
            'class_subject_teacher_id' => $assignment->id,
            'day' => 'Monday',
        ]);
    }

    /**
     * Test can check schedule conflicts
     */
    public function test_can_check_schedule_conflicts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $assignment = ClassSubjectTeacher::factory()->create();

        // Create existing schedule
        Schedule::factory()->create([
            'class_subject_teacher_id' => $assignment->id,
            'day' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'Room 101',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/schedules/check-conflicts', [
                'class_subject_teacher_id' => $assignment->id,
                'day' => 'Monday',
                'start_time' => '08:30',
                'end_time' => '09:30',
                'room' => 'Room 101',
            ]);

        $response->assertStatus(200);
    }

    /**
     * Test can get class schedule
     */
    public function test_can_get_class_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $assignment = ClassSubjectTeacher::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes/' . $assignment->class_id . '/schedule');

        $response->assertStatus(200);
    }

    /**
     * Test can get teacher schedule
     */
    public function test_can_get_teacher_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $assignment = ClassSubjectTeacher::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/teachers/' . $assignment->teacher_id . '/schedule');

        $response->assertStatus(200);
    }

    /**
     * Test can get weekly overview
     */
    public function test_can_get_weekly_overview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/schedules/weekly-overview');

        $response->assertStatus(200);
    }

    /**
     * Test can bulk create schedules
     */
    public function test_can_bulk_create_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $assignments = ClassSubjectTeacher::factory()->count(3)->create();

        $schedules = $assignments->map(function ($assignment) {
            return [
                'class_subject_teacher_id' => $assignment->id,
                'day' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'room' => 'Room 101',
            ];
        })->toArray();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/schedules/bulk', [
                'schedules' => $schedules,
            ]);

        $response->assertStatus(201);
    }

    /**
     * Test teacher cannot create schedule
     */
    public function test_teacher_cannot_create_schedule(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/schedules', [
                'class_subject_teacher_id' => 1,
                'day' => 'Monday',
            ]);

        $response->assertStatus(403);
    }
}
