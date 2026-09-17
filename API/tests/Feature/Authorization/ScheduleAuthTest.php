<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that schedule write operations are restricted to admin-tier roles.
 * Parents and accountants must be blocked entirely.
 * Teachers may VIEW schedules but cannot create/modify/delete them.
 */
class ScheduleAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Roles blocked from all schedule write operations ───────────────────────

    /**
     * @dataProvider rolesBlockedFromScheduleWrites
     */
    public function test_role_cannot_create_schedule(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/schedules', [])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromScheduleWrites
     */
    public function test_role_cannot_update_schedule(string $role): void
    {
        $user     = User::factory()->create(['role' => $role]);
        $token    = $user->createToken('test')->plainTextToken;
        $schedule = Schedule::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/schedules/' . $schedule->id, ['day' => 'Monday'])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromScheduleWrites
     */
    public function test_role_cannot_delete_schedule(string $role): void
    {
        $user     = User::factory()->create(['role' => $role]);
        $token    = $user->createToken('test')->plainTextToken;
        $schedule = Schedule::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/schedules/' . $schedule->id)
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromScheduleWrites
     */
    public function test_role_cannot_bulk_create_schedules(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/schedules/bulk', ['schedules' => []])
            ->assertStatus(403);
    }

    /**
     * @dataProvider rolesBlockedFromScheduleWrites
     */
    public function test_role_cannot_generate_schedules(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/schedules/generate', [])
            ->assertStatus(403);
    }

    // ── Roles blocked from listing schedules entirely ──────────────────────────

    /**
     * @dataProvider rolesBlockedFromScheduleList
     */
    public function test_role_cannot_list_schedules(string $role): void
    {
        $user  = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/schedules')
            ->assertStatus(403);
    }

    // ── Admin can manage schedules ─────────────────────────────────────────────

    public function test_admin_can_list_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/schedules')
            ->assertStatus(200);
    }

    // ── Teacher can view schedule endpoints ────────────────────────────────────

    public function test_teacher_can_view_their_own_schedule(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token   = $teacher->createToken('test')->plainTextToken;

        // /api/my-schedule is the shared read-only schedule endpoint
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/my-schedule')
            ->assertStatus(200);
    }

    // ── Data providers ─────────────────────────────────────────────────────────

    public static function rolesBlockedFromScheduleWrites(): array
    {
        // Route group 2: admin,secretariat,...,accountant — accountant IS allowed
        // Schedule write endpoints sit inside route group 2:
        return [
            'teacher'    => ['teacher'],
            'parent'     => ['parent'],
            'supervisor' => ['supervisor'],
        ];
    }

    public static function rolesBlockedFromScheduleList(): array
    {
        // /api/schedules index is in route group 2.
        // Parent is NOT in route group 2 and has no /api/schedules route.
        return [
            'parent' => ['parent'],
        ];
    }
}
