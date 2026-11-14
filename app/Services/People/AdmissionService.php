<?php

namespace App\Services\People;

use App\Enums\AdmissionStatus;
use App\Enums\StudentStatus;
use App\Models\AdmissionApplication;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admission workflow: pending → approved | rejected; approved → enrolled.
 */
class AdmissionService
{
    public function __construct(
        private ReferenceNumberGenerator $numbers,
        private EnrollmentService $enrollments,
        private GuardianService $guardians,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $actor, int $schoolId, array $data): AdmissionApplication
    {
        return $this->numbers->withNext('application', $schoolId, fn (string $number): AdmissionApplication => AdmissionApplication::query()->create([
            ...$data,
            'school_id' => $schoolId,
            'application_no' => $number,
            'status' => AdmissionStatus::Pending,
            'created_by' => $actor->id,
        ]));
    }

    public function decide(AdmissionApplication $application, User $reviewer, AdmissionStatus $decision, ?string $notes): void
    {
        if ($application->status !== AdmissionStatus::Pending) {
            throw ValidationException::withMessages(['status' => __('Only pending applications can be approved or rejected.')]);
        }

        if (! in_array($decision, [AdmissionStatus::Approved, AdmissionStatus::Rejected], true)) {
            throw ValidationException::withMessages(['status' => __('Invalid decision.')]);
        }

        $application->forceFill([
            'status' => $decision,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'decision_notes' => $notes,
        ])->save();
    }

    /**
     * Turn an approved application into a student with a guardian and an enrollment.
     */
    public function enroll(AdmissionApplication $application, Section $section, ?string $admissionNo): Student
    {
        if ($application->status !== AdmissionStatus::Approved) {
            throw ValidationException::withMessages(['status' => __('Only approved applications can be enrolled.')]);
        }

        if ($section->school_class_id !== $application->school_class_id) {
            throw ValidationException::withMessages(['section_id' => __('Choose a section of :class.', ['class' => $application->schoolClass->name])]);
        }

        $create = fn (string $number): Student => DB::transaction(function () use ($application, $section, $number): Student {
            $student = Student::query()->create([
                'school_id' => $application->school_id,
                'admission_no' => $number,
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'gender' => $application->gender,
                'date_of_birth' => $application->date_of_birth->toDateString(),
                'admission_date' => now()->toDateString(),
                'status' => StudentStatus::Active,
            ]);

            $this->guardians->link($student, $this->findOrCreateGuardian($application), $application->guardian_relationship, true);
            $this->enrollments->enroll($student, $application->academicYear, $section);

            $application->forceFill(['status' => AdmissionStatus::Enrolled, 'student_id' => $student->id])->save();

            return $student;
        });

        return $admissionNo !== null
            ? $create($admissionNo)
            : $this->numbers->withNext('student', $application->school_id, $create);
    }

    /**
     * Reuse an existing guardian (siblings) matched by exact phone or email within the school.
     */
    private function findOrCreateGuardian(AdmissionApplication $application): Guardian
    {
        $phoneIndex = (new Guardian)->blindIndexFor('phone', $application->guardian_phone);

        $existing = Guardian::query()
            ->where(function ($query) use ($phoneIndex, $application): void {
                $query->where('phone_index', $phoneIndex);

                if ($application->guardian_email !== null) {
                    $query->orWhere('email', Str::lower($application->guardian_email));
                }
            })
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $first = Str::before($application->guardian_name, ' ');
        $last = trim(Str::after($application->guardian_name, ' '));

        return Guardian::query()->create([
            'school_id' => $application->school_id,
            'first_name' => $first,
            'last_name' => $last !== '' && $last !== $first ? $last : '-',
            'email' => $application->guardian_email !== null ? Str::lower($application->guardian_email) : null,
            'phone' => $application->guardian_phone,
        ]);
    }
}
