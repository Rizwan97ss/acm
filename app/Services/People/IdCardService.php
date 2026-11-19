<?php

namespace App\Services\People;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Services\Records\QrCodeRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Data for printable ID cards and their public verification.
 *
 * Each person gets a random card token; the QR code on the card opens /verify/id/{token},
 * which shows only what is printed on the card. Reissuing a card (for example when one is
 * lost) replaces the token, so the old card no longer verifies.
 */
class IdCardService
{
    public function __construct(private QrCodeRenderer $qr) {}

    public function token(Student|Guardian|Employee $person): string
    {
        if ($person->card_token === null) {
            $person->forceFill(['card_token' => Str::random(40)])->saveQuietly();
        }

        return (string) $person->card_token;
    }

    public function reissue(Student|Guardian|Employee $person): void
    {
        $person->forceFill(['card_token' => Str::random(40)])->saveQuietly();
    }

    public function validUntil(): CarbonImmutable
    {
        $year = AcademicYear::query()->current()->first();

        return $year !== null ? $year->ends_on : CarbonImmutable::today()->addYear();
    }

    /**
     * @return array<string, mixed>
     */
    public function student(Student $student): array
    {
        $student->loadMissing(['currentEnrollment.section.schoolClass', 'guardians']);
        $section = $student->currentEnrollment?->section;
        $guardian = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();

        return $this->card($student, 'student', $student->full_name, $student->admission_no, [
            'role' => __('Student'),
            'subtitle' => $section !== null ? $section->fullName() : null,
            'fields' => array_filter([
                __('Date of birth') => $student->date_of_birth->format('d M Y'),
                __('Blood group') => $student->blood_group,
            ]),
            'emergency' => $guardian !== null ? ['name' => $guardian->full_name, 'phone' => $guardian->phone] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function employee(Employee $employee): array
    {
        return $this->card($employee, 'staff', $employee->full_name, $employee->employee_no, [
            'role' => $employee->is_teaching ? __('Teaching staff') : __('Staff'),
            'subtitle' => $employee->designation,
            'fields' => array_filter([
                __('Department') => $employee->department,
                __('Joined') => $employee->joined_on->format('M Y'),
                __('Phone') => $employee->phone,
            ]),
            'emergency' => null,
        ]);
    }

    /**
     * A parent's card lists every child at the school.
     *
     * @return array<string, mixed>
     */
    public function guardian(Guardian $guardian): array
    {
        $guardian->loadMissing(['students.currentEnrollment.section.schoolClass']);

        return $this->card($guardian, 'parent', $guardian->full_name, sprintf('P-%06d', $guardian->id), [
            'role' => __('Parent / Guardian'),
            'subtitle' => $guardian->occupation,
            'fields' => array_filter([__('Phone') => $guardian->phone, __('Email') => $guardian->email]),
            'emergency' => null,
            'children' => $guardian->students->map(fn (Student $student): array => [
                'name' => $student->full_name,
                'admission_no' => $student->admission_no,
                'class' => $student->currentEnrollment?->section?->fullName(),
            ])->values()->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function card(Student|Guardian|Employee $person, string $kind, string $name, string $number, array $details): array
    {
        $verifyUrl = route('id-cards.verify', $this->token($person));
        $folder = match ($kind) {
            'student' => 'students',
            'staff' => 'staff',
            default => 'guardians',
        };

        return [
            'key' => "{$kind}-{$person->id}",
            'kind' => $kind,
            'name' => $name,
            'number' => $number,
            'photo_url' => $person->photo_path !== null ? route('people.photos.show', ['kind' => $folder, 'id' => $person->id, 'v' => substr(md5($person->photo_path), 0, 8)]) : null,
            'initials' => Str::upper(Str::substr($name, 0, 1).Str::substr((string) Str::of($name)->afterLast(' '), 0, 1)),
            'valid_until' => $this->validUntil()->toDateString(),
            'verify_url' => $verifyUrl,
            'qr_svg' => $this->qr->svg($verifyUrl),
            ...$details,
        ];
    }

    /**
     * @return array{name: string, address: string|null, phone: string|null, email: string|null, logo_url: string|null}
     */
    public function school(?School $school): array
    {
        return [
            'name' => $school->name ?? (string) config('app.name'),
            'address' => $school?->address,
            'phone' => $school?->phone,
            'email' => $school?->email,
            'logo_url' => $school?->logo_url,
        ];
    }
}
