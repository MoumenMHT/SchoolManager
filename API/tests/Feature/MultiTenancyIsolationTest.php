<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_is_applied_automatically(): void
    {
        // 1. Create a second tenant
        $otherTenant = Tenant::create([
            'id' => 'other_school',
            'data' => ['name' => 'Other School']
        ]);

        // 2. Create data in the other tenant
        tenancy()->initialize($otherTenant);
        $otherStudent = Student::factory()->create(['tenant_id' => $otherTenant->id]);
        tenancy()->end();

        // 3. Initialize default tenant and check query scopes
        tenancy()->initialize($this->tenant);
        $studentInDefaultTenant = Student::factory()->create();

        // Should only see the student in the default tenant
        $allStudents = Student::all();
        $this->assertCount(1, $allStudents);
        $this->assertEquals($studentInDefaultTenant->id, $allStudents->first()->id);
    }

    public function test_models_auto_assign_tenant_id_on_creation(): void
    {
        tenancy()->initialize($this->tenant);
        
        $student = Student::factory()->create(); // without explicitly passing tenant_id
        
        $this->assertEquals($this->tenant->id, $student->tenant_id);
    }

    public function test_cross_tenant_data_leakage_prevented_on_api(): void
    {
        // 1. Create a second tenant
        $otherTenant = Tenant::create([
            'id' => 'other_school',
            'data' => ['name' => 'Other School']
        ]);

        // 2. Create data in the other tenant
        tenancy()->initialize($otherTenant);
        $otherStudent = Student::factory()->create(['tenant_id' => $otherTenant->id]);
        tenancy()->end();

        // 3. Authenticate as a user from the default test_school tenant
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => $this->tenant->id]);
        
        // 4. Attempt to access the other tenant's student via API
        $response = $this->actingAs($user, 'web')
            ->getJson("/api/t/{$this->tenant->id}/students/{$otherStudent->id}");

        // Should return 404 Not Found because the global scope isolates it within the controller
        $response->assertStatus(404);
    }

    public function test_api_fails_with_invalid_tenant_id(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => $this->tenant->id]);
        
        $response = $this->actingAs($user, 'web')
            ->getJson("/api/t/nonexistent_school/students");

        // Assuming InitializeTenancyByPath throws an exception or returns 404
        $response->assertStatus(404); // InitializeTenancyByPath returns a 404 or fails if tenant doesn't exist
    }
}
