<?php

namespace Database\Factories;

use App\Models\MaterialUsage;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialUsage>
 */
class MaterialUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'raw_material_id' => RawMaterial::factory(),
            'usage_date' => fake()->unique()->dateTimeBetween('-90 days', 'now'),
            'quantity' => fake()->randomFloat(2, 1, 30),
        ];
    }
}
