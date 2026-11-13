<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableEntry>
 */
class TimetableEntryFactory extends Factory
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
            'period_id' => fn (array $attributes) => Period::factory()->state(['school_id' => $attributes['school_id']]),
            'subject_id' => fn (array $attributes) => Subject::factory()->state(['school_id' => $attributes['school_id']]),
            'day_of_week' => Weekday::Monday,
            'teacher_id' => null,
            'room' => null,
        ];
    }
}
