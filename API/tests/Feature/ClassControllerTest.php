<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can list classes
     */
    public function test_admin_can_list_classes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        SchoolClass::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/classes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test admin can create class
     */
    public function test_admin_can_create_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/classes', [
                'name' => '6ème A',
                'level' => '6ème',
                'academic_year' => '2025-2026',
                'capacity' => 30,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('classes', [
            'name' => '6ème A',
            'level' => '6ème',
        ]);
    }

    /**
     * Test admin can update class
     */
    public function test_admin_can_update_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $class = SchoolClass::factory()->create(['capacity' => 25]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/classes/' . $class->id, [
                'name' => $class->name,
                'level' => $class->level,
                'academic_year' => $class->academic_year,
                'capacity' => 35,
                'is_active' => true,
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'capacity' => 35,
        ]);
    }

    /**
     * Test admin can delete class
     */
    public function test_admin_can_delete_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $class = SchoolClass::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/classes/' . $class->id);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('classes', [
            'id' => $class->id,
        ]);
    }

    /**
     * Test class name is required
     */
    public function test_class_name_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/classes', [
                'level' => '6ème',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
