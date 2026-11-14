<?php

namespace Database\Factories;

use App\Enums\Audience;
use App\Models\School;
use App\Models\SchoolEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolEvent>
 */
class SchoolEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfDay()->addDays(fake()->numberBetween(1, 20))->setTime(9, 0);

        return [
            'school_id' => School::factory(),
            'title' => fake()->sentence(3),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'all_day' => false,
            'audience' => Audience::Everyone,
        ];
    }
}
