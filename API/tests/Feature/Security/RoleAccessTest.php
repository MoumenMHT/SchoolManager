<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_cannot_delete_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'tenant_id' => $this->tenant->id]);
        $token = $teacher->createToken('test')->plainTextToken;

        $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/students/' . $student->id);

        // Teacher is unauthorized to delete students
        $response->assertStatus(403);
    }

    public function test_parent_cannot_view_all_students(): void
    {
        $parent = User::factory()->create(['role' => 'parent', 'tenant_id' => $this->tenant->id]);
        $token = $parent->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students');

        // Parent is unauthorized to list all students
        $response->assertStatus(403);
    }
}
