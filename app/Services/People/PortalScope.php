<?php

namespace App\Services\People;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Narrows what students and guardians can see to their own (or their children's) records.
 * Staff are never narrowed, even when they are also a parent at the school.
 */
class PortalScope
{
    public function isPortalUser(User $user): bool
    {
        return ! $user->employee()->withoutGlobalScopes()->exists()
            && ($user->student()->withoutGlobalScopes()->exists() || $user->guardian()->withoutGlobalScopes()->exists());
    }

    /**
     * Sections the user may see for the year, or null when the user is not restricted.
     *
     * @return list<int>|null
     */
    public function sectionIds(User $user, ?AcademicYear $year): ?array
    {
        if (! $this->isPortalUser($user)) {
            return null;
        }

        if ($year === null) {
            return [];
        }

        $studentIds = $this->studentIds($user);

        $sectionIds = Enrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_year_id', $year->id)
            ->distinct()
            ->pluck('section_id')
            ->all();

        return array_values(array_map(intval(...), $sectionIds));
    }

    /**
     * The student records the user may see: themselves, or their linked children.
     *
     * @return list<int>
     */
    public function studentIds(User $user): array
    {
        $ids = [];

        if (($student = $user->student()->first()) !== null) {
            $ids[] = $student->id;
        }

        if (($guardian = $user->guardian()->first()) !== null) {
            $ids = [...$ids, ...$guardian->students()->pluck('students.id')->all()];
        }

        return array_values(array_unique(array_map(intval(...), $ids)));
    }
}
