<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        $startYear = $this->faker->unique()->numberBetween(1970, 2030);
        if (\Illuminate\Support\Facades\Schema::hasTable('tenants')) {
            \Illuminate\Support\Facades\DB::table('tenants')->insertOrIgnore([
                'id' => 'school1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return [
            'name'       => $startYear . '-' . ($startYear + 1),
            'start_date' => $startYear . '-09-01',
            'end_date'   => ($startYear + 1) . '-06-30',
            'is_current' => $this->faker->boolean(),
            'tenant_id'  => 'school1',
        ];
    }
}
