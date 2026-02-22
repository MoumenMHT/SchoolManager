<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can list subjects
     */
    public function test_admin_can_list_subjects(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        Subject::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/subjects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can create subject
     */
    public function test_admin_can_create_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/subjects', [
                'name' => 'Mathematics',
                'code' => 'MATH',
                'description' => 'Mathematics for 6ème',
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH',
        ]);
    }

    /**
     * Test admin can update subject
     */
    public function test_admin_can_update_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $subject = Subject::factory()->create(['name' => 'Old Name', 'code' => 'OLDCODE']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/subjects/' . $subject->id, [
                'name' => 'New Name',
                'code' => 'NEWCODE',
                'description' => $subject->description,
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'New Name',
        ]);
    }

    /**
     * Test admin can delete subject
     */
    public function test_admin_can_delete_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $subject = Subject::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/subjects/' . $subject->id);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('subjects', [
            'id' => $subject->id,
        ]);
    }

    /**
     * Test subject code must be unique
     */
    public function test_subject_code_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        Subject::factory()->create(['code' => 'MATH']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/subjects', [
                'name' => 'Another Math',
                'code' => 'MATH',
                'description' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    /**
     * Test authenticated users can view subjects
     */
    public function test_authenticated_users_can_view_subjects(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token = $teacher->createToken('test-token')->plainTextToken;

        Subject::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/subjects');

        $response->assertStatus(200);
    }
}
