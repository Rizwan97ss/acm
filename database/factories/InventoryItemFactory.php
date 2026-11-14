<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->words(2, true),
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'category' => 'Stationery',
            'unit' => 'pcs',
            'quantity' => 0,
            'reorder_level' => 5,
        ];
    }
}
