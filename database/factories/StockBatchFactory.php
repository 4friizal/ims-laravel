<?php

namespace Database\Factories;

use App\Models\RawMaterial;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBatch>
 */
class StockBatchFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 10, 100);

        return [
            'raw_material_id' => RawMaterial::factory(),
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => fake()->randomFloat(2, 5000, 80000),
            'received_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'note' => null,
        ];
    }
}
