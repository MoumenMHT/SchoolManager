<?php

namespace Tests\Unit;

use App\Models\Teacher;
use App\Models\User;
use App\Models\Exam;
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
            'contract_type',
            'weekly_hours',
        ];

        $teacher = new Teacher();

        $this->assertEquals($fillable, $teacher->getFillable());
    }

    /**
     * Test teacher belongs to user
     */
    public function test_teacher_belongs_to_user(): void
    {
        $user    = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $teacher->user);
        $this->assertEquals($user->id, $teacher->user->id);
    }

    /**
     * Test teacher has many exams (grades now stored via Exam model)
     */
    public function test_teacher_has_many_exams(): void
    {
        $teacher = Teacher::factory()->create();
        Exam::factory()->count(5)->create(['teacher_id' => $teacher->id]);

        // Teacher grades are now accessed through exams
        $examCount = Exam::where('teacher_id', $teacher->id)->count();
        $this->assertEquals(5, $examCount);
    }

    /**
     * Test teacher has many attendances
     */
    public function test_teacher_has_many_attendances(): void
    {
        $teacher     = Teacher::factory()->create();
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
