<?php

namespace Database\Factories;

use App\Models\ParentModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParentModelFactory extends Factory
{
    protected $model = ParentModel::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => 'parent']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'cin' => fake()->optional()->regexify('[A-Z]{2}[0-9]{6}'),
            'profession' => fake()->optional()->randomElement(['Engineer', 'Doctor', 'Teacher', 'Business Owner', 'Lawyer']),
        ];
    }
}
