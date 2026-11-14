<?php

namespace App\Services\Academics;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\TimetableEntry;
use App\Models\User;

/**
 * Which sections and subjects a user may work with in attendance, marks and assignments.
 *
 * Holding "{module}.approve" makes a user a supervisor of that module across the school.
 * Everyone else works only with sections they are class teacher of or teach on the
 * timetable, and with marks only for subjects they teach there (class teachers: all).
 */
class TeachingScope
{
    /**
     * @var array<string, list<int>>
     */
    private array $sectionCache = [];

    public function supervises(User $user, string $module): bool
    {
        return $user->hasPermission("{$module}.approve");
    }

    /**
     * @return list<int>|null null when the user supervises the module (all sections)
     */
    public function sectionIds(User $user, AcademicYear $year, string $module): ?array
    {
        if ($this->supervises($user, $module)) {
            return null;
        }

        return $this->sectionCache["{$user->id}:{$year->id}"] ??= $this->taughtSectionIds($user, $year);
    }

    public function canAccessSection(User $user, Section $section, AcademicYear $year, string $module): bool
    {
        $allowed = $this->sectionIds($user, $year, $module);

        return $allowed === null || in_array($section->id, $allowed, true);
    }

    /**
     * Subject-level access, used for marks: the subject teacher or the section's class teacher.
     */
    public function canTeachSubject(User $user, Section $section, int $subjectId, AcademicYear $year, string $module): bool
    {
        if ($this->supervises($user, $module) || $section->class_teacher_id === $user->id) {
            return true;
        }

        return TimetableEntry::query()
            ->where('academic_year_id', $year->id)
            ->where('section_id', $section->id)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $user->id)
            ->exists();
    }

    /**
     * @return list<int>
     */
    private function taughtSectionIds(User $user, AcademicYear $year): array
    {
        $asClassTeacher = Section::query()->where('class_teacher_id', $user->id)->pluck('id')->all();
        $onTimetable = TimetableEntry::query()
            ->where('academic_year_id', $year->id)
            ->where('teacher_id', $user->id)
            ->distinct()
            ->pluck('section_id')
            ->all();

        return array_values(array_unique(array_map(intval(...), [...$asClassTeacher, ...$onTimetable])));
    }
}
