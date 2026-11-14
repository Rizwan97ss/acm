<?php

namespace App\Services\People;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StudentService
{
    public function __construct(
        private ReferenceNumberGenerator $numbers,
        private EnrollmentService $enrollments,
    ) {}

    /**
     * Create a student, generating the admission number when none is given, and
     * optionally enroll them straight away.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{academic_year_id: int, section_id: int}|null  $enrollment
     */
    public function create(int $schoolId, array $attributes, ?array $enrollment): Student
    {
        $create = fn (string $number): Student => DB::transaction(function () use ($schoolId, $attributes, $enrollment, $number): Student {
            $student = Student::query()->create([
                ...Arr::except($attributes, ['admission_no']),
                'school_id' => $schoolId,
                'admission_no' => $number,
            ]);

            if ($enrollment !== null) {
                $this->enrollments->enroll(
                    $student,
                    AcademicYear::query()->findOrFail($enrollment['academic_year_id']),
                    Section::query()->findOrFail($enrollment['section_id']),
                );
            }

            return $student;
        });

        $admissionNo = $attributes['admission_no'] ?? null;

        return is_string($admissionNo) && $admissionNo !== ''
            ? $create($admissionNo)
            : $this->numbers->withNext('student', $schoolId, $create);
    }

    /**
     * Archive a student. Their portal account is deactivated so they can no longer sign in.
     */
    public function delete(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            $student->user?->update(['is_active' => false]);
            $student->delete();
        });
    }
}
