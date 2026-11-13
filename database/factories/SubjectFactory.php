<?php

namespace Database\Factories;

use App\Enums\SubjectType;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => Str::title(fake()->unique()->word()).' Studies',
            'code' => Str::upper(fake()->unique()->bothify('???-##')),
            'type' => SubjectType::Core,
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
