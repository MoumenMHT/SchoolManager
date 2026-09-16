<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExtendedIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_cannot_view_other_parents_contracts_or_payments(): void
    {
        // Parent 1 and their child's contract
        $user1 = User::factory()->create(['role' => 'parent']);
        $parent1 = ParentModel::factory()->create(['user_id' => $user1->id]);
        $student1 = Student::factory()->create(['parent_id' => $parent1->id]);
        
        $contract1 = Contract::factory()->create([
            'parent_id' => $parent1->id,
            'contract_number' => 'CNT-001',
            'total_fees' => 1000
        ]);

        $payment1 = Payment::factory()->create([
            'contract_id' => $contract1->id,
            'amount' => 500
        ]);

        // Parent 2 and their child's contract
        $user2 = User::factory()->create(['role' => 'parent']);
        $parent2 = ParentModel::factory()->create(['user_id' => $user2->id]);
        $student2 = Student::factory()->create(['parent_id' => $parent2->id]);
        
        $contract2 = Contract::factory()->create([
            'parent_id' => $parent2->id,
            'contract_number' => 'CNT-002',
            'total_fees' => 2000
        ]);

        $payment2 = Payment::factory()->create([
            'contract_id' => $contract2->id,
            'amount' => 1000
        ]);

        // Act & Assert
        // Parent 1 tries to access Parent 2's contract (using the parent endpoint)
        $response = $this->actingAs($user1, 'web')
            ->getJson("/api/parent/contracts/{$contract2->id}");
        $response->assertStatus(403);

        // Parent 1 tries to access Parent 2's student payments
        $response = $this->actingAs($user1, 'web')
            ->getJson("/api/parent/students/{$student2->id}/payments");
        $response->assertStatus(404);

        // Parent 1 CAN access their own contract
        $response = $this->actingAs($user1, 'web')
            ->getJson("/api/parent/contracts/{$contract1->id}");
        $response->assertStatus(200);

        // Parent 1 CAN access their own student's payments
        $response = $this->actingAs($user1, 'web')
            ->getJson("/api/parent/students/{$student1->id}/payments");
        $response->assertStatus(200);
    }
}
