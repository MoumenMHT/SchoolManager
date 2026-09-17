<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Supervisor;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that only the supervisor role can access the supervisor portal.
 * All other roles (except admin where shared) must receive 403.
 */
class SupervisorPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Roles blocked from supervisor portal ───────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromSupervisorPortal
     */
    public function test_role_cannot_access_supervisor_classes(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/supervisor/classes')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromSupervisorPortal
     */
    public function test_role_cannot_access_supervisor_dashboard(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/supervisor/dashboard')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromSupervisorPortal
     */
    public function test_role_cannot_access_supervisor_class_schedule(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;
        $class = SchoolClass::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/supervisor/classes/' . $class->id . '/schedule-today')
            ->assertStatus(403);
    }

    // ── Supervisor cannot access teacher-only grade/attendance write endpoints ──

    public function test_supervisor_cannot_submit_grades(): void
    {
        $user  = User::factory()->create(['role' => 'supervisor']);
        $token = $user->createToken('test')->plainTextToken;

        // Supervisor can call the route (middleware allows it via teacher,supervisor,admin group)
        // but cannot actually create a grade for a class they don't own.
        // We just verify they hit the route (200 or 422 from validation, not 403 at route level).
        // The deeper test (business logic restriction) is in GradeAuthTest.
        // Here we verify supervisor is NOT blocked at the *route* middleware level for grades.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/grades', [])
            ->assertStatus(422); // validation error, not 403 — supervisor CAN call this route
    }

    // ── Supervisor CAN access their own portal ─────────────────────────────────

    public function test_supervisor_can_access_own_classes(): void
    {
        $user       = User::factory()->create(['role' => 'supervisor']);
        $supervisor = Supervisor::create([
            'user_id'    => $user->id,
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'status'     => 'active',
            'tenant_id'  => $user->tenant_id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/supervisor/classes')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromSupervisorPortal(): array
    {
        return [
            'teacher'          => ['teacher'],
            'parent'           => ['parent'],
            'accountant'       => ['accountant'],
            'secretariat'      => ['secretariat'],
            'primary_director' => ['primary_director'],
            'cem_director'     => ['cem_director'],
            'lycee_director'   => ['lycee_director'],
        ];
    }
}
