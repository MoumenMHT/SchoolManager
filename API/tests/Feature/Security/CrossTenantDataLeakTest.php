<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantDataLeakTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_cannot_access_other_tenant_data(): void
    {
        // 1. Create a second tenant
        $otherTenant = Tenant::create([
            'id' => 'other_school',
            'data' => ['name' => 'Other School']
        ]);

        // 2. Create data in the other tenant
        // Temporarily switch context to create data
        tenancy()->initialize($otherTenant);
        
        $otherUser = User::factory()->create(['role' => 'admin', 'tenant_id' => $otherTenant->id]);
        $otherStudent = Student::factory()->create(['tenant_id' => $otherTenant->id]);
        
        tenancy()->end();

        // 3. Authenticate as a user from the default test_school tenant
        tenancy()->initialize($this->tenant);
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => $this->tenant->id]);
        $token = $user->createToken('test')->plainTextToken;

        // 4. Attempt to access the other tenant's student via API
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/students/' . $otherStudent->id);

        // Should return 404 Not Found because the global scope isolates it
        $response->assertStatus(404);
    }
}
