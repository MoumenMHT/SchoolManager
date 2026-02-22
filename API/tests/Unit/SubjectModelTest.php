<?php

namespace Tests\Unit;

use App\Models\Subject;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test subject has correct fillable fields
     */
    public function test_subject_has_correct_fillable_fields(): void
    {
        $fillable = ['name', 'code', 'description'];
        $subject = new Subject();
        
        $this->assertEquals($fillable, $subject->getFillable());
    }

    /**
     * Test subject has many grades
     */
    public function test_subject_has_many_grades(): void
    {
        $subject = Subject::factory()->create();
        Grade::factory()->count(10)->create(['subject_id' => $subject->id]);
        
        $this->assertCount(10, $subject->grades);
    }

    /**
     * Test subject can be created
     */
    public function test_subject_can_be_created(): void
    {
        $subject = Subject::factory()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
        ]);
        
        $this->assertDatabaseHas('subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH',
        ]);
    }

    /**
     * Test subject code is unique
     */
    public function test_subject_code_is_unique(): void
    {
        Subject::factory()->create(['code' => 'MATH']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        Subject::factory()->create(['code' => 'MATH']);
    }
}
