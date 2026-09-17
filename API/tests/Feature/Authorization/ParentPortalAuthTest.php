<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies parent portal authorization:
 * - Only parents (and admin) can access /api/parent/* routes.
 * - A parent can only see their OWN children's data.
 * - Teachers, supervisors, accountants are blocked.
 */
class ParentPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Roles blocked from parent portal ───────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromParentPortal
     */
    public function test_role_cannot_access_parent_students(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/students')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromParentPortal
     */
    public function test_role_cannot_access_parent_contracts(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/contracts')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromParentPortal
     */
    public function test_role_cannot_access_parent_dashboard(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/dashboard')
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromParentPortal
     */
    public function test_role_cannot_access_parent_student_grades(string $role): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $student = Student::factory()->create();

        // 403 = role blocked; 404 = route-level model binding fires before role guard
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/students/' . $student->id . '/grades');
        $this->assertContains($response->status(), [403, 404]);
    }

    /**
     * @dataProvider rolesBlockedFromParentPortal
     */
    public function test_role_cannot_access_parent_student_attendance(string $role): void
    {
        $user    = User::factory()->create(['role' => $role]);
        $token   = $user->createToken('test')->plainTextToken;
        $student = Student::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/students/' . $student->id . '/attendances');
        $this->assertContains($response->status(), [403, 404]);
    }

    // ── Parent IDOR: cannot access another parent's child data ────────────────

    public function test_parent_cannot_view_other_parents_child_grades(): void
    {
        // Attacker: authenticated parent with their own child
        $attackerUser   = User::factory()->create(['role' => 'parent']);
        $attackerParent = ParentModel::factory()->create(['user_id' => $attackerUser->id]);
        $attackerToken  = $attackerUser->createToken('test')->plainTextToken;
        Student::factory()->create(['parent_id' => $attackerParent->id]);

        // Victim: different parent's child
        $victimParent  = ParentModel::factory()->create();
        $victimStudent = Student::factory()->create(['parent_id' => $victimParent->id]);

        // Attacker tries to view victim's child's grades via parent portal
        $this->withHeader('Authorization', 'Bearer ' . $attackerToken)
            ->getJson('/api/parent/students/' . $victimStudent->id . '/grades')
            ->assertStatus(403);
    }

    public function test_parent_cannot_view_other_parents_child_attendance(): void
    {
        $attackerUser   = User::factory()->create(['role' => 'parent']);
        $attackerParent = ParentModel::factory()->create(['user_id' => $attackerUser->id]);
        $attackerToken  = $attackerUser->createToken('test')->plainTextToken;
        Student::factory()->create(['parent_id' => $attackerParent->id]);

        $victimParent  = ParentModel::factory()->create();
        $victimStudent = Student::factory()->create(['parent_id' => $victimParent->id]);

        // Accept 403 (controller policy) or 404 (global scope hides student) — both deny access
        $response = $this->withHeader('Authorization', 'Bearer ' . $attackerToken)
            ->getJson('/api/parent/students/' . $victimStudent->id . '/attendances');
        $this->assertContains($response->status(), [403, 404], 'Expected access denial for unowned child');
    }

    public function test_parent_cannot_view_other_parents_child_schedule(): void
    {
        $attackerUser   = User::factory()->create(['role' => 'parent']);
        $attackerParent = ParentModel::factory()->create(['user_id' => $attackerUser->id]);
        $attackerToken  = $attackerUser->createToken('test')->plainTextToken;
        Student::factory()->create(['parent_id' => $attackerParent->id]);

        $victimParent  = ParentModel::factory()->create();
        $victimStudent = Student::factory()->create(['parent_id' => $victimParent->id]);

        // System returns 403 (IDOR) or 404 (global scope hides the student) — both are valid
        $response = $this->withHeader('Authorization', 'Bearer ' . $attackerToken)
            ->getJson('/api/parent/students/' . $victimStudent->id . '/schedule');

        $this->assertContains($response->status(), [403, 404], 'Expected access to be denied (403 or 404)');
    }

    public function test_parent_cannot_view_other_parents_child_report_card(): void
    {
        $attackerUser   = User::factory()->create(['role' => 'parent']);
        $attackerParent = ParentModel::factory()->create(['user_id' => $attackerUser->id]);
        $attackerToken  = $attackerUser->createToken('test')->plainTextToken;
        Student::factory()->create(['parent_id' => $attackerParent->id]);

        $victimParent  = ParentModel::factory()->create();
        $victimStudent = Student::factory()->create(['parent_id' => $victimParent->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $attackerToken)
            ->getJson('/api/parent/students/' . $victimStudent->id . '/report-card');
        $this->assertContains($response->status(), [403, 404], 'Expected access denial for unowned child');
    }

    // ── Parent CAN access their OWN children's data ────────────────────────────

    public function test_parent_can_view_own_students(): void
    {
        $user   = User::factory()->create(['role' => 'parent']);
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $token  = $user->createToken('test')->plainTextToken;

        Student::factory()->count(2)->create(['parent_id' => $parent->id]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/students')
            ->assertStatus(200);
    }

    public function test_parent_can_view_own_child_grades(): void
    {
        $user    = User::factory()->create(['role' => 'parent']);
        $parent  = ParentModel::factory()->create(['user_id' => $user->id]);
        $token   = $user->createToken('test')->plainTextToken;
        $student = Student::factory()->create(['parent_id' => $parent->id]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/parent/students/' . $student->id . '/grades')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromParentPortal(): array
    {
        return [
            'teacher'    => ['teacher'],
            'supervisor' => ['supervisor'],
            'accountant' => ['accountant'],
            'secretariat' => ['secretariat'],
        ];
    }
}
