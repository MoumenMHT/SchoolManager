<?php

namespace Tests\Feature\Security;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Fee;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ensures every protected route group returns 401 Unauthorized
 * when no authentication token is provided.
 */
class UnauthenticatedAccessTest extends TestCase
{
    use RefreshDatabase;

    // ── Auth endpoints ────────────────────────────────────

    public function test_unauthenticated_cannot_access_me(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_logout(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_change_password(): void
    {
        $this->postJson('/api/change-password', [])->assertStatus(401);
    }

    // ── User management ───────────────────────────────────

    public function test_unauthenticated_cannot_register(): void
    {
        $this->postJson('/api/register', [])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }

    // ── Student management ────────────────────────────────

    public function test_unauthenticated_cannot_list_students(): void
    {
        $this->getJson('/api/students')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_student(): void
    {
        $this->postJson('/api/students', [])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_view_student(): void
    {
        $student = Student::factory()->create();
        $this->getJson('/api/students/' . $student->id)->assertStatus(401);
    }

    public function test_unauthenticated_cannot_delete_student(): void
    {
        $student = Student::factory()->create();
        $this->deleteJson('/api/students/' . $student->id)->assertStatus(401);
    }

    // ── Teacher management ────────────────────────────────

    public function test_unauthenticated_cannot_list_teachers(): void
    {
        $this->getJson('/api/teachers')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_teacher(): void
    {
        $this->postJson('/api/teachers', [])->assertStatus(401);
    }

    // ── Class management ──────────────────────────────────

    public function test_unauthenticated_cannot_list_classes(): void
    {
        $this->getJson('/api/classes')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_class(): void
    {
        $this->postJson('/api/classes', [])->assertStatus(401);
    }

    // ── Subject management (write) ────────────────────────

    public function test_unauthenticated_cannot_create_subject(): void
    {
        $this->postJson('/api/subjects', [])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_delete_subject(): void
    {
        $subject = Subject::factory()->create();
        $this->deleteJson('/api/subjects/' . $subject->id)->assertStatus(401);
    }

    // ── Payment system ────────────────────────────────────

    public function test_unauthenticated_cannot_list_fees(): void
    {
        $this->getJson('/api/fees')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_fee(): void
    {
        $this->postJson('/api/fees', [])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_list_contracts(): void
    {
        $this->getJson('/api/contracts')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_contract(): void
    {
        $this->postJson('/api/contracts', [])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_list_payments(): void
    {
        $this->getJson('/api/payments')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_payment(): void
    {
        $this->postJson('/api/payments', [])->assertStatus(401);
    }

    // ── Grades / Attendance ───────────────────────────────

    public function test_unauthenticated_cannot_list_grades(): void
    {
        $this->getJson('/api/grades')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_grade(): void
    {
        $this->postJson('/api/grades', [])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_list_attendances(): void
    {
        $this->getJson('/api/attendances')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_attendance(): void
    {
        $this->postJson('/api/attendances', [])->assertStatus(401);
    }

    // ── Supervisor portal ─────────────────────────────────

    public function test_unauthenticated_cannot_access_supervisor_classes(): void
    {
        $this->getJson('/api/supervisor/classes')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_access_supervisor_dashboard(): void
    {
        $this->getJson('/api/supervisor/dashboard')->assertStatus(401);
    }

    // ── Parent portal ─────────────────────────────────────

    public function test_unauthenticated_cannot_access_parent_students(): void
    {
        $this->getJson('/api/parent/students')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_access_parent_contracts(): void
    {
        $this->getJson('/api/parent/contracts')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_access_parent_dashboard(): void
    {
        $this->getJson('/api/parent/dashboard')->assertStatus(401);
    }

    // ── Dashboard ─────────────────────────────────────────

    public function test_unauthenticated_cannot_access_dashboard_stats(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
    }

    // ── Schedule management ───────────────────────────────

    public function test_unauthenticated_cannot_list_schedules(): void
    {
        $this->getJson('/api/schedules')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_schedule(): void
    {
        $this->postJson('/api/schedules', [])->assertStatus(401);
    }
}
