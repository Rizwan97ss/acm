<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\TransportRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportRoute>
 */
class TransportRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Route '.fake()->unique()->numberBetween(1, 9999),
            'vehicle_no' => fake()->bothify('BUS-###'),
            'driver_name' => fake()->name(),
            'capacity' => 30,
        ];
    }
}
