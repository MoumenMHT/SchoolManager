<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\ParentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can list all students
     */
    public function test_admin_can_list_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        Student::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can create student
     */
    public function test_admin_can_create_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $class = SchoolClass::factory()->create();
        $parent = ParentModel::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/students', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'code' => 'STU001',
                'birth_date' => '2010-01-15',
                'gender' => 'male',
                'class_id' => $class->id,
                'parent_id' => $parent->id,
                'enrollment_date' => now()->format('Y-m-d'),
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('students', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'code' => 'STU001',
        ]);
    }

    /**
     * Test admin can view single student
     */
    public function test_admin_can_view_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $student->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test admin can update student
     */
    public function test_admin_can_update_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create(['first_name' => 'Old Name']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/students/' . $student->id, [
                'first_name' => 'New Name',
                'last_name' => $student->last_name,
                'code' => $student->code,
                'birth_date' => $student->birth_date->format('Y-m-d'),
                'gender' => $student->gender,
                'class_id' => $student->class_id,
                'parent_id' => $student->parent_id,
                'enrollment_date' => $student->enrollment_date->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'New Name',
        ]);
    }

    /**
     * Test admin can delete student
     */
    public function test_admin_can_delete_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/students/' . $student->id);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('students', [
            'id' => $student->id,
        ]);
    }

    /**
     * Test teacher cannot create student
     */
    public function test_teacher_cannot_create_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/students', [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test validation fails with invalid data
     */
    public function test_validation_fails_with_invalid_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/students', [
                'first_name' => '',
                'last_name' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }
}
