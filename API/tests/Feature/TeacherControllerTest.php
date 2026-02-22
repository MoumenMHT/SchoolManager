<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can list all teachers
     */
    public function test_admin_can_list_teachers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        Teacher::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/teachers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can create teacher
     */
    public function test_admin_can_create_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $user = User::factory()->create(['role' => 'teacher']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/teachers', [
                'user_id' => $user->id,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'cin' => 'AB123456',
                'birth_date' => '1990-05-15',
                'specialization' => 'Mathematics',
                'hire_date' => '2020-09-01',
                'salary' => 5000.00,
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('teachers', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }

    /**
     * Test admin can update teacher
     */
    public function test_admin_can_update_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $teacher = Teacher::factory()->create(['specialization' => 'Physics']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/teachers/' . $teacher->id, [
                'user_id' => $teacher->user_id,
                'first_name' => $teacher->first_name,
                'last_name' => $teacher->last_name,
                'specialization' => 'Mathematics',
                'hire_date' => $teacher->hire_date->format('Y-m-d'),
                'salary' => 6000.00,
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'specialization' => 'Mathematics',
        ]);
    }

    /**
     * Test admin can delete teacher
     */
    public function test_admin_can_delete_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $teacher = Teacher::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/teachers/' . $teacher->id);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('teachers', [
            'id' => $teacher->id,
        ]);
    }

    /**
     * Test teacher cannot delete another teacher
     */
    public function test_teacher_cannot_delete_another_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token = $teacher->createToken('test-token')->plainTextToken;

        $otherTeacher = Teacher::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/teachers/' . $otherTeacher->id);

        $response->assertStatus(403);
    }
}
