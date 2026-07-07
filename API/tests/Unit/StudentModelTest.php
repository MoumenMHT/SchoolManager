<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\ParentModel;
use App\Models\Grade;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test student has required fillable attributes
     */
    public function test_student_has_fillable_attributes(): void
    {
        $fillable = [
            'first_name',
            'last_name',
            'code',
            'birth_date',
            'gender',
            'class_id',
            'parent_id',
            'enrollment_date',
            'medical_info',
            'is_active',
        ];

        $student = new Student();

        $this->assertEquals($fillable, $student->getFillable());
    }

    /**
     * Test student belongs to a class
     */
    public function test_student_belongs_to_class(): void
    {
        $class   = SchoolClass::factory()->create();
        $student = Student::factory()->create(['class_id' => $class->id]);

        $this->assertInstanceOf(SchoolClass::class, $student->class);
        $this->assertEquals($class->id, $student->class->id);
    }

    /**
     * Test student belongs to a parent
     */
    public function test_student_belongs_to_parent(): void
    {
        $parent  = ParentModel::factory()->create();
        $student = Student::factory()->create(['parent_id' => $parent->id]);

        $this->assertInstanceOf(ParentModel::class, $student->parent);
        $this->assertEquals($parent->id, $student->parent->id);
    }

    /**
     * Test student has many grades
     */
    public function test_student_has_many_grades(): void
    {
        $student = Student::factory()->create();
        Grade::factory()->count(3)->create(['student_id' => $student->id]);

        $this->assertCount(3, $student->grades);
        $this->assertInstanceOf(Grade::class, $student->grades->first());
    }

    /**
     * Test student has many attendances
     */
    public function test_student_has_many_attendances(): void
    {
        $student     = Student::factory()->create();
        $attendances = Attendance::factory()->count(5)->create(['student_id' => $student->id]);

        $this->assertCount(5, $student->attendances);
        $this->assertInstanceOf(Attendance::class, $student->attendances->first());
    }

    /**
     * Test student full name accessor
     */
    public function test_student_full_name_accessor(): void
    {
        $student = Student::factory()->create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        $this->assertEquals('John Doe', $student->full_name);
    }

    /**
     * Test student date casting
     */
    public function test_student_date_casting(): void
    {
        $student = Student::factory()->create([
            'birth_date'      => '2010-01-15',
            'enrollment_date' => '2024-09-01',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $student->birth_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $student->enrollment_date);
    }
}
