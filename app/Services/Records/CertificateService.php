<?php

namespace App\Services\Records;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\Certificate;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\People\ReferenceNumberGenerator;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    public function __construct(
        private ReferenceNumberGenerator $numbers,
        private CurrentSchool $currentSchool,
        private AuditLogger $audit,
    ) {}

    /**
     * A draft pre-filled from the type's template; the text stays editable until issued.
     */
    public function draft(Student $student, CertificateType $type, User $actor): Certificate
    {
        [$title, $body] = $this->template($student, $type);

        return $this->numbers->withNext('certificate', $student->school_id, fn (string $serial): Certificate => Certificate::query()->create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'type' => $type,
            'serial_no' => $serial,
            'title' => $title,
            'body' => $body,
            'verification_code' => Str::random(32),
            'created_by' => $actor->id,
        ]));
    }

    public function issue(Certificate $certificate, User $actor): void
    {
        if ($certificate->status !== CertificateStatus::Draft) {
            throw ValidationException::withMessages(['certificate' => __('Only drafts can be issued.')]);
        }

        $certificate->forceFill(['status' => CertificateStatus::Issued, 'issued_on' => now()->toDateString(), 'issued_by' => $actor->id])->save();
        $this->audit->log('certificate.issued', $certificate);
    }

    public function revoke(Certificate $certificate, string $reason): void
    {
        if ($certificate->status !== CertificateStatus::Issued) {
            throw ValidationException::withMessages(['certificate' => __('Only issued certificates can be revoked.')]);
        }

        $certificate->forceFill(['status' => CertificateStatus::Revoked, 'revoked_at' => now(), 'revoke_reason' => $reason])->save();
        $this->audit->log('certificate.revoked', $certificate, [], ['reason' => $reason]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function template(Student $student, CertificateType $type): array
    {
        $enrollment = $student->currentEnrollment()->with(['section.schoolClass', 'academicYear'])->first();
        $name = "{$student->first_name} {$student->last_name}";
        $school = (string) ($this->currentSchool->get()->name ?? config('app.name'));
        $class = $enrollment !== null ? trim("{$enrollment->section?->schoolClass?->name} {$enrollment->section?->name}") : null;
        $year = $enrollment?->academicYear?->name;
        $studying = $class ? " is a bona fide student of {$school}, studying in class {$class}".($year ? " during the academic year {$year}" : '') : " is a student of {$school}";

        return match ($type) {
            CertificateType::Bonafide => ['Bonafide Certificate', "This is to certify that {$name} (admission no. {$student->admission_no}){$studying}."],
            CertificateType::Character => ['Character Certificate', "This is to certify that {$name} (admission no. {$student->admission_no}){$studying}. To the best of our knowledge, {$name} bears a good moral character and conduct."],
            CertificateType::Transfer => ['Transfer Certificate', "This is to certify that {$name} (admission no. {$student->admission_no}) was a student of {$school}".($class ? " in class {$class}" : '').", admitted on {$student->admission_date->format('d M Y')}. All dues have been cleared and the school has no objection to the student joining another institution."],
            CertificateType::Achievement => ['Certificate of Achievement', "Awarded to {$name}".($class ? " of class {$class}" : '').' in recognition of outstanding achievement in [describe the achievement].'],
        };
    }
}
