<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Level;
use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that level and academic year write operations are restricted to
 * admin-tier roles (admin, secretariat, directors, accountant).
 * Teachers, parents, and supervisors must receive 403 on mutating operations.
 */
class LevelAndAcademicYearAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Level write operations ─────────────────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromLevelWrites
     */
    public function test_role_cannot_create_level(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/levels', ['name' => 'Terminale', 'order' => 7])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromLevelWrites
     */
    public function test_role_cannot_update_level(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;
        static $seq = 0;
        $level = \App\Models\Level::create(['cycle' => 'Primaire', 'year_number' => ++$seq, 'name' => 'CP' . $seq]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/levels/' . $level->id, ['name' => 'Changed'])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromLevelWrites
     */
    public function test_role_cannot_delete_level(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;
        static $seq = 0;
        $level = \App\Models\Level::create(['cycle' => 'Primaire', 'year_number' => ++$seq, 'name' => 'CP' . $seq]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/levels/' . $level->id)
            ->assertStatus(403);
    }

    // ── Level read — all authenticated users can list levels ───────────────────

    /**
     * @dataProvider allRoles
     */
    public function test_all_authenticated_roles_can_list_levels(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/levels')
            ->assertStatus(200);
    }

    // ── Academic Year write operations ─────────────────────────────────────────

    /**
     * @dataProvider rolesBlockedFromAcademicYearWrites
     */
    public function test_role_cannot_create_academic_year(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/academic-years', [
                'name'       => '2027-2028',
                'start_date' => '2027-09-01',
                'end_date'   => '2028-06-30',
            ])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromAcademicYearWrites
     */
    public function test_role_cannot_update_academic_year(string $role): void
    {
        $user         = User::factory()->create(['role' => $role]);
        $token        = $user->createToken('test')->plainTextToken;
        $academicYear = AcademicYear::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/academic-years/' . $academicYear->id, ['name' => 'Changed'])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromAcademicYearWrites
     */
    public function test_role_cannot_delete_academic_year(string $role): void
    {
        $user         = User::factory()->create(['role' => $role]);
        $token        = $user->createToken('test')->plainTextToken;
        $academicYear = AcademicYear::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/academic-years/' . $academicYear->id)
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromAcademicYearWrites
     */
    public function test_role_cannot_set_current_academic_year(string $role): void
    {
        $user         = User::factory()->create(['role' => $role]);
        $token        = $user->createToken('test')->plainTextToken;
        $academicYear = AcademicYear::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/academic-years/' . $academicYear->id . '/current')
            ->assertStatus(403);
    }

    // ── Authorized roles CAN access ────────────────────────────────────────────

    public function test_admin_can_create_academic_year(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/academic-years', [
                'name'       => '2028-2029',
                'start_date' => '2028-09-01',
                'end_date'   => '2029-06-30',
            ])
            ->assertStatus(201);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromLevelWrites(): array
    {
        // Route group 2: admin,secretariat,primary_director,cem_director,lycee_director,accountant
        // Only teacher/parent/supervisor are NOT in this group:
        return [
            'teacher'    => ['teacher'],
            'parent'     => ['parent'],
            'supervisor' => ['supervisor'],
        ];
    }

    public static function rolesBlockedFromAcademicYearWrites(): array
    {
        return [
            'teacher'    => ['teacher'],
            'parent'     => ['parent'],
            'supervisor' => ['supervisor'],
        ];
    }

    public static function allRoles(): array
    {
        return [
            'admin'       => ['admin'],
            'secretariat' => ['secretariat'],
            'teacher'     => ['teacher'],
            'parent'      => ['parent'],
            'supervisor'  => ['supervisor'],
            'accountant'  => ['accountant'],
        ];
    }
}
