<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\Contract;
use App\Models\Fee;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContractSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test cannot bind unrelated student to parent contract.
     */
    public function test_cannot_bind_unrelated_student_to_parent_contract(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('test-token')->plainTextToken;

        $parentA = ParentModel::factory()->create();
        $studentA = Student::factory()->create(['parent_id' => $parentA->id]);

        $parentB = ParentModel::factory()->create();
        // studentB belongs to parentB, NOT parentA
        $studentB = Student::factory()->create(['parent_id' => $parentB->id]);

        $fee = Fee::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/contracts', [
                'parent_id' => $parentA->id,
                'academic_year' => '2023-2024',
                'start_date' => '2023-09-01',
                'end_date' => '2024-06-30',
                'student_fees' => [
                    [
                        'student_id' => $studentB->id,
                        'fee_ids' => [$fee->id]
                    ]
                ]
            ]);

        // It should reject the request with 403 because studentB does not belong to parentA.
        $response->assertStatus(403);
        
        $this->assertDatabaseMissing('contracts', [
            'parent_id' => $parentA->id,
        ]);
    }

    /**
     * Test parent cannot view other parents' contract.
     */
    public function test_parent_cannot_view_other_parents_contract(): void
    {
        $user = User::factory()->create(['role' => 'parent']);
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $otherParent = ParentModel::factory()->create();
        $otherContract = Contract::factory()->create(['parent_id' => $otherParent->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/contracts/' . $otherContract->id);

        // Expect 403 Forbidden
        $response->assertStatus(403);
    }
}
