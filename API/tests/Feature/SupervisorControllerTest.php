<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_my_classes_returns_only_active_academic_year_classes(): void
    {
        $user = User::factory()->create(['role' => 'supervisor']);
        $supervisor = Supervisor::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active',
            'tenant_id' => $user->tenant_id,
        ]);

        $oldYear = AcademicYear::create([
            'name' => '2024-2025',
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30',
            'is_current' => false,
            'tenant_id' => $user->tenant_id,
        ]);

        $currentYear = AcademicYear::create([
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => true,
            'tenant_id' => $user->tenant_id,
        ]);

        $oldClass = SchoolClass::create([
            'name' => 'Class Old',
            'level' => '10th',
            'academic_year_id' => $oldYear->id,
            'supervisor_id' => $supervisor->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $currentClass = SchoolClass::create([
            'name' => 'Class Current',
            'level' => '11th',
            'academic_year_id' => $currentYear->id,
            'supervisor_id' => $supervisor->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/supervisor/classes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $classIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($currentClass->id, $classIds);
        $this->assertNotContains($oldClass->id, $classIds);
    }

    public function test_supervisor_dashboard_returns_only_active_academic_year_classes(): void
    {
        $user = User::factory()->create(['role' => 'supervisor']);
        $supervisor = Supervisor::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active',
            'tenant_id' => $user->tenant_id,
        ]);

        $oldYear = AcademicYear::create([
            'name' => '2024-2025',
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30',
            'is_current' => false,
            'tenant_id' => $user->tenant_id,
        ]);

        $currentYear = AcademicYear::create([
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => true,
            'tenant_id' => $user->tenant_id,
        ]);

        $oldClass = SchoolClass::create([
            'name' => 'Class Old',
            'level' => '10th',
            'academic_year_id' => $oldYear->id,
            'supervisor_id' => $supervisor->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $currentClass = SchoolClass::create([
            'name' => 'Class Current',
            'level' => '11th',
            'academic_year_id' => $currentYear->id,
            'supervisor_id' => $supervisor->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/supervisor/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $dashClassIds = collect($response->json('data'))->pluck('class_id')->all();

        $this->assertContains($currentClass->id, $dashClassIds);
        $this->assertNotContains($oldClass->id, $dashClassIds);
    }
}
