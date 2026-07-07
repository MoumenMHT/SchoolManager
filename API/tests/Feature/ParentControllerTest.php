<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can list parents
     */
    public function test_admin_can_list_parents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        ParentModel::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parents');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can create parent
     */
    public function test_admin_can_create_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $user = User::factory()->create(['role' => 'parent']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/parents', [
                'user_id' => $user->id,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => 612345678,
                'email' => 'parent@example.com',
                'cin' => 123456,
                'profession' => 'Engineer',
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('parents', [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    /**
     * Test parent can view their children
     */
    public function test_parent_can_view_their_children(): void
    {
        $user = User::factory()->create(['role' => 'parent']);
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        Student::factory()->count(2)->create(['parent_id' => $parent->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/students');

        $response->assertStatus(200);
    }

    /**
     * Test admin can update parent
     */
    public function test_admin_can_update_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $parent = ParentModel::factory()->create(['profession' => 'Doctor']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/parents/' . $parent->id, [
                'user_id' => $parent->user_id,
                'first_name' => $parent->first_name,
                'last_name' => $parent->last_name,
                'phone' => 612345678,
                'profession' => 'Engineer',
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('parents', [
            'id' => $parent->id,
            'profession' => 'Engineer',
        ]);
    }

    /**
     * Test admin can delete parent
     */
    public function test_admin_can_delete_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $parent = ParentModel::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/parents/' . $parent->id);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('parents', [
            'id' => $parent->id,
        ]);
    }
}
