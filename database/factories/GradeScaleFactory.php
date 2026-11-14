<?php

namespace Database\Factories;

use App\Models\GradeScale;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeScale>
 */
class GradeScaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => ucfirst(fake()->unique()->word()).' scale',
            'is_default' => false,
        ];
    }
}
