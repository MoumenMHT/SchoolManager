<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Payment;
use App\Models\Attendance;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can access dashboard statistics
     */
    public function test_admin_can_access_dashboard_statistics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        // Create some test data
        Student::factory()->count(5)->create();
        Teacher::factory()->count(3)->create();
        SchoolClass::factory()->count(2)->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test teacher cannot access dashboard statistics
     */
    public function test_teacher_cannot_access_dashboard_statistics(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token = $teacher->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(403);
    }

    /**
     * Test parent cannot access dashboard statistics
     */
    public function test_parent_cannot_access_dashboard_statistics(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $token = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot access dashboard
     */
    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(401);
    }
}
