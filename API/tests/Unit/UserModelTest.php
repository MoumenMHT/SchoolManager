<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Teacher;
use App\Models\ParentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user has required fillable attributes
     */
    public function test_user_has_fillable_attributes(): void
    {
        $fillable = [
            'username',
            'email',
            'password',
            'role',
            'phone',
            'address',
            'is_active',
        ];

        $user = new User();

        $this->assertEquals($fillable, $user->getFillable());
    }

    /**
     * Test user password is hidden
     */
    public function test_user_password_is_hidden(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret'),
        ]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
    }

    /**
     * Test user can be admin
     */
    public function test_user_can_be_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertEquals('admin', $admin->role);
    }

    /**
     * Test user can be teacher
     */
    public function test_user_can_be_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->assertEquals('teacher', $teacher->role);
    }

    /**
     * Test user can be parent
     */
    public function test_user_can_be_parent(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);

        $this->assertEquals('parent', $parent->role);
    }

    /**
     * Test user has teacher relationship
     */
    public function test_user_has_teacher_relationship(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(Teacher::class, $user->teacher);
        $this->assertEquals($teacher->id, $user->teacher->id);
    }

    /**
     * Test user has parent relationship
     */
    public function test_user_has_parent_relationship(): void
    {
        $user = User::factory()->create(['role' => 'parent']);
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(ParentModel::class, $user->parent);
        $this->assertEquals($parent->id, $user->parent->id);
    }
}
