<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enrollment = Enrollment::factory()->create();

        return [
            'school_id' => $enrollment->school_id,
            'academic_year_id' => $enrollment->academic_year_id,
            'section_id' => $enrollment->section_id,
            'student_id' => $enrollment->student_id,
            'date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
        ];
    }
}
