<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'school_id' => fn (array $attributes) => Section::query()->withoutGlobalScopes()->whereKey($attributes['section_id'])->value('school_id'),
            'student_id' => fn (array $attributes) => Student::factory()->state(['school_id' => $attributes['school_id']]),
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->state(['school_id' => $attributes['school_id']]),
            'enrolled_on' => now()->toDateString(),
            'status' => EnrollmentStatus::Active,
        ];
    }
}
