<?php

namespace Tests\Unit;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClassModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test class has correct fillable fields
     */
    public function test_class_has_correct_fillable_fields(): void
    {
        $fillable = ['name', 'level', 'level_id', 'academic_year', 'capacity', 'main_teacher_id', 'is_active'];
        $class = new SchoolClass();
        
        $this->assertEquals($fillable, $class->getFillable());
    }

    /**
     * Test class has many students
     */
    public function test_class_has_many_students(): void
    {
        $class = SchoolClass::factory()->create();
        Student::factory()->count(5)->create(['class_id' => $class->id]);
        
        $this->assertCount(5, $class->students);
    }

    /**
     * Test class casts is_active to boolean
     */
    public function test_class_casts_is_active_to_boolean(): void
    {
        $class = SchoolClass::factory()->create(['is_active' => true]);
        
        $this->assertIsBool($class->is_active);
        $this->assertTrue($class->is_active);
    }

    /**
     * Test class can be created
     */
    public function test_class_can_be_created(): void
    {
        $class = SchoolClass::factory()->create([
            'name' => '6ème A',
            'level' => '6ème',
        ]);
        
        $this->assertDatabaseHas('classes', [
            'name' => '6ème A',
            'level' => '6ème',
        ]);
    }
}
