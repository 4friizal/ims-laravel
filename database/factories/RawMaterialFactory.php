<?php

namespace Database\Factories;

use App\Enums\MaterialUnit;
use App\Models\Category;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawMaterial>
 */
class RawMaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(2, true),
            'unit' => fake()->randomElement(MaterialUnit::cases()),
            'minimum_stock' => fake()->randomFloat(2, 1, 20),
        ];
    }
}
