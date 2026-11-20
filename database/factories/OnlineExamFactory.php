<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\AcademicYear;
use App\Models\OnlineExam;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineExam>
 */
class OnlineExamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->state(['school_id' => $attributes['school_id']]),
            'school_class_id' => fn (array $attributes) => SchoolClass::factory()->state(['school_id' => $attributes['school_id']]),
            'subject_id' => fn (array $attributes) => Subject::factory()->state(['school_id' => $attributes['school_id']]),
            'title' => 'Unit test '.fake()->numberBetween(1, 20),
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'duration_minutes' => 45,
            'total_marks' => 10,
            'passing_marks' => 4,
            'max_attempts' => 1,
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => PublishStatus::Published]);
    }
}
