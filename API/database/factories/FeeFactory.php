<?php

namespace Database\Factories;

use App\Models\Fee;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeeFactory extends Factory
{
    protected $model = Fee::class;

    public function definition(): array
    {
        $names = [
            'Tuition Fee'   => 3500,
            'Transportation' => 800,
            'Lunch Program'  => 600,
            'Activity Fee'   => 400,
            'Library Fee'    => 250,
        ];
        $name   = fake()->randomElement(array_keys($names));
        $amount = $names[$name];

        return [
            'name'          => $name,
            'description'   => fake()->sentence(),
            'base_amount'   => $amount + fake()->numberBetween(-200, 200),
            'academic_year' => '2025-2026',
            'is_active'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function previousYear(): static
    {
        return $this->state(fn () => [
            'academic_year' => '2024-2025',
            'is_active'     => false,
        ]);
    }
}
