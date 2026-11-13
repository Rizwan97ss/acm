<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->unique()->numberBetween(2000, 2090);

        return [
            'school_id' => School::factory(),
            'name' => "{$start}-".($start + 1),
            'starts_on' => "{$start}-09-01",
            'ends_on' => ($start + 1).'-06-30',
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => ['is_current' => true]);
    }
}
