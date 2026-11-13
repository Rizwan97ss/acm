<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'school_id' => fn (array $attributes) => SchoolClass::query()->withoutGlobalScopes()->whereKey($attributes['school_class_id'])->value('school_id'),
            'name' => fake()->unique()->bothify('?#'),
            'capacity' => 30,
            'room' => fake()->bothify('R-###'),
        ];
    }
}
