<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\GradeScale;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->state(['school_id' => $attributes['school_id']]),
            'grade_scale_id' => fn (array $attributes) => GradeScale::factory()->state(['school_id' => $attributes['school_id']]),
            'name' => 'Mid-term examination',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
        ];
    }
}
