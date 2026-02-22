<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['6ème A', '6ème B', '5ème A', '4ème A', '3ème A']),
            'level' => fake()->randomElement(['6ème', '5ème', '4ème', '3ème']),
            'academic_year' => '2025-2026',
            'capacity' => fake()->numberBetween(25, 35),
            'is_active' => true,
        ];
    }
}
