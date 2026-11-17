<?php

namespace App\Services\People;

use App\Enums\GuardianRelationship;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Links guardians to students. Every student with guardians has exactly one primary guardian.
 */
class GuardianService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * An existing guardian of the current school (siblings share one) matched by exact phone
     * (through its blind index) or email.
     */
    public function findMatching(?string $phone, ?string $email): ?Guardian
    {
        $phoneIndex = $phone !== null && $phone !== '' ? (new Guardian)->blindIndexFor('phone', $phone) : null;
        $email = $email !== null && $email !== '' ? Str::lower(trim($email)) : null;

        if ($phoneIndex === null && $email === null) {
            return null;
        }

        return Guardian::query()
            ->where(function ($query) use ($phoneIndex, $email): void {
                if ($phoneIndex !== null) {
                    $query->where('phone_index', $phoneIndex);
                }

                if ($email !== null) {
                    $query->orWhere('email', $email);
                }
            })
            ->first();
    }

    public function link(Student $student, Guardian $guardian, GuardianRelationship $relationship, bool $isPrimary, bool $isEmergencyContact = true): void
    {
        if ($guardian->school_id !== $student->school_id) {
            throw ValidationException::withMessages(['guardian_id' => __('The guardian must belong to the student\'s school.')]);
        }

        DB::transaction(function () use ($student, $guardian, $relationship, $isPrimary, $isEmergencyContact): void {
            $makePrimary = $isPrimary || ! $student->guardians()->wherePivot('is_primary', true)->whereKeyNot($guardian->id)->exists();

            if ($makePrimary) {
                $student->guardians()->newPivotStatement()
                    ->where('student_id', $student->id)
                    ->update(['is_primary' => false]);
            }

            $student->guardians()->syncWithoutDetaching([
                $guardian->id => [
                    'relationship' => $relationship->value,
                    'is_primary' => $makePrimary,
                    'is_emergency_contact' => $isEmergencyContact,
                ],
            ]);
        });

        $this->audit->log('student.guardian_linked', $student, [], [
            'guardian_id' => $guardian->id,
            'relationship' => $relationship->value,
            'is_primary' => $isPrimary,
        ]);
    }

    /**
     * Remove the link; if the primary guardian is removed another guardian becomes primary.
     */
    public function unlink(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian): void {
            $student->guardians()->detach($guardian->id);

            if (! $student->guardians()->wherePivot('is_primary', true)->exists()) {
                $next = $student->guardians()->first();

                if ($next !== null) {
                    $student->guardians()->updateExistingPivot($next->id, ['is_primary' => true]);
                }
            }
        });

        $this->audit->log('student.guardian_unlinked', $student, ['guardian_id' => $guardian->id]);
    }
}
