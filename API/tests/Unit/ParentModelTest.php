<?php

namespace Tests\Unit;

use App\Models\ParentModel;
use App\Models\User;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test parent has correct fillable fields
     */
    public function test_parent_has_correct_fillable_fields(): void
    {
        $fillable = ['user_id', 'first_name', 'last_name', 'phone', 'email', 'cin', 'profession'];
        $parent = new ParentModel();
        
        $this->assertEquals($fillable, $parent->getFillable());
    }

    /**
     * Test parent belongs to user
     */
    public function test_parent_belongs_to_user(): void
    {
        $user = User::factory()->create(['role' => 'parent']);
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        
        $this->assertInstanceOf(User::class, $parent->user);
        $this->assertEquals($user->id, $parent->user->id);
    }

    /**
     * Test parent has many children
     */
    public function test_parent_has_many_children(): void
    {
        $parent = ParentModel::factory()->create();
        Student::factory()->count(3)->create(['parent_id' => $parent->id]);
        
        $this->assertCount(3, $parent->students);
    }

    /**
     * Test parent can be created
     */
    public function test_parent_can_be_created(): void
    {
        $user = User::factory()->create(['role' => 'parent']);
        $parent = ParentModel::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Parent',
        ]);
        
        $this->assertDatabaseHas('parents', [
            'first_name' => 'Test',
            'last_name' => 'Parent',
        ]);
    }
}
