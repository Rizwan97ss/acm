<?php

namespace App\Services\People;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Places a student in a section for an academic year (one enrollment per year).
 * Enrolling again in the same year transfers the student to the new section.
 */
class EnrollmentService
{
    public function enroll(Student $student, AcademicYear $year, Section $section, ?int $rollNumber = null): Enrollment
    {
        return DB::transaction(function () use ($student, $year, $section, $rollNumber): Enrollment {
            if ($section->school_id !== $student->school_id || $year->school_id !== $student->school_id) {
                throw ValidationException::withMessages(['section_id' => __('The section must belong to the student\'s school.')]);
            }

            $enrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $year->id)
                ->lockForUpdate()
                ->first() ?? new Enrollment([
                    'school_id' => $student->school_id,
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'enrolled_on' => now()->toDateString(),
                ]);

            $changingSection = $enrollment->section_id !== $section->id;

            if ($changingSection) {
                $this->guardCapacity($section, $year);
            }

            if ($rollNumber !== null) {
                $this->guardRollNumber($section, $year, $rollNumber, $enrollment);
            } elseif ($changingSection || $enrollment->roll_number === null) {
                $rollNumber = $this->nextRollNumber($section, $year);
            }

            $enrollment->fill([
                'section_id' => $section->id,
                'roll_number' => $rollNumber ?? $enrollment->roll_number,
                'status' => EnrollmentStatus::Active,
            ])->save();

            return $enrollment;
        });
    }

    private function guardCapacity(Section $section, AcademicYear $year): void
    {
        if ($section->capacity === null) {
            return;
        }

        $occupied = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active)
            ->lockForUpdate()
            ->count();

        if ($occupied >= $section->capacity) {
            throw ValidationException::withMessages([
                'section_id' => __('Section :section is full (:capacity students).', ['section' => $section->name, 'capacity' => $section->capacity]),
            ]);
        }
    }

    private function guardRollNumber(Section $section, AcademicYear $year, int $rollNumber, Enrollment $enrollment): void
    {
        $taken = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->where('roll_number', $rollNumber)
            ->when($enrollment->exists, fn ($query) => $query->whereKeyNot($enrollment->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['roll_number' => __('Roll number :number is already taken in this section.', ['number' => $rollNumber])]);
        }
    }

    private function nextRollNumber(Section $section, AcademicYear $year): int
    {
        return (int) Enrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->max('roll_number') + 1;
    }
}
