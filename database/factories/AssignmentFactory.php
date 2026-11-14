<?php

namespace Database\Factories;

use App\Enums\AssignmentType;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'school_id' => fn (array $attributes) => Section::query()->withoutGlobalScopes()->whereKey($attributes['section_id'])->value('school_id'),
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->state(['school_id' => $attributes['school_id']]),
            'subject_id' => fn (array $attributes) => Subject::factory()->state(['school_id' => $attributes['school_id']]),
            'type' => AssignmentType::Homework,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'due_on' => now()->addDays(5)->toDateString(),
            'max_marks' => 10,
        ];
    }
}
