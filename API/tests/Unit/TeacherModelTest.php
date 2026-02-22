<?php

namespace Tests\Unit;

use App\Models\Teacher;
use App\Models\User;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test teacher has required fillable attributes
     */
    public function test_teacher_has_fillable_attributes(): void
    {
        $fillable = [
            'user_id',
            'first_name',
            'last_name',
            'cin',
            'birth_date',
            'specialization',
            'hire_date',
            'salary',
        ];

        $teacher = new Teacher();

        $this->assertEquals($fillable, $teacher->getFillable());
    }

    /**
     * Test teacher belongs to user
     */
    public function test_teacher_belongs_to_user(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $teacher->user);
        $this->assertEquals($user->id, $teacher->user->id);
    }

    /**
     * Test teacher has many grades
     */
    public function test_teacher_has_many_grades(): void
    {
        $teacher = Teacher::factory()->create();
        $grades = Grade::factory()->count(5)->create(['teacher_id' => $teacher->id]);

        $this->assertCount(5, $teacher->grades);
        $this->assertInstanceOf(Grade::class, $teacher->grades->first());
    }

    /**
     * Test teacher has many attendances
     */
    public function test_teacher_has_many_attendances(): void
    {
        $teacher = Teacher::factory()->create();
        $attendances = Attendance::factory()->count(10)->create(['teacher_id' => $teacher->id]);

        $this->assertCount(10, $teacher->attendances);
        $this->assertInstanceOf(Attendance::class, $teacher->attendances->first());
    }

    /**
     * Test teacher hire date casting
     */
    public function test_teacher_hire_date_casting(): void
    {
        $teacher = Teacher::factory()->create([
            'hire_date' => '2020-09-01',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $teacher->hire_date);
    }
}
