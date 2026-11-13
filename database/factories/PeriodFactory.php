<?php

namespace Database\Factories;

use App\Models\Period;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 200);
        $hour = 8 + ($position % 8);

        return [
            'school_id' => School::factory(),
            'name' => "Period {$position}",
            'starts_at' => sprintf('%02d:00', $hour),
            'ends_at' => sprintf('%02d:45', $hour),
            'is_break' => false,
            'position' => $position,
        ];
    }

    public function break(): static
    {
        return $this->state(fn (array $attributes) => ['is_break' => true, 'name' => 'Break']);
    }
}
