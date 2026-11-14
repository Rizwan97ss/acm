<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\Dashboard\DashboardWidget;
use App\Services\People\PortalScope;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;

/**
 * Today's lessons from the current academic year: a teacher's own lessons, a student's
 * section timetable, or each of a guardian's children's timetables.
 */
class TodaysLessonsWidget implements DashboardWidget
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private SettingsService $settings,
        private PortalScope $portalScope,
    ) {}

    public function key(): string
    {
        return 'todaysLessons';
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->currentSchool->id() === null || ! $user->hasPermission('timetables.view')) {
            return false;
        }

        return $this->portalScope->isPortalUser($user)
            || TimetableEntry::query()->where('teacher_id', $user->id)->exists();
    }

    public function isDeferred(): bool
    {
        return false;
    }

    /**
     * @return list<array{period: string, starts_at: string, ends_at: string, subject: string, color: string|null, section: string, room: string|null}>
     */
    public function data(User $user): array
    {
        $year = AcademicYear::query()->current()->first();

        if ($year === null) {
            return [];
        }

        $today = now()->setTimezone((string) $this->settings->current('localization.timezone'))->isoWeekday();
        $isPortalUser = $this->portalScope->isPortalUser($user);

        /** @var array<int, list<string>> $childrenBySection */
        $childrenBySection = [];

        if ($isPortalUser) {
            $enrollments = Enrollment::query()
                ->with('student:id,first_name,last_name')
                ->whereIn('student_id', $this->portalScope->studentIds($user))
                ->where('academic_year_id', $year->id)
                ->get();

            foreach ($enrollments as $enrollment) {
                $childrenBySection[$enrollment->section_id][] = $enrollment->student->first_name;
            }
        }

        $lessons = TimetableEntry::query()
            ->with(['period', 'subject:id,name,color', 'section.schoolClass:id,name'])
            ->where('academic_year_id', $year->id)
            ->where('day_of_week', $today)
            ->when(
                $isPortalUser,
                fn (Builder $query) => $query->whereIn('section_id', array_keys($childrenBySection)),
                fn (Builder $query) => $query->where('teacher_id', $user->id),
            )
            ->get()
            ->sortBy(fn (TimetableEntry $entry): string => sprintf('%03d-%d', $entry->period->position, $entry->section_id))
            ->map(fn (TimetableEntry $entry): array => [
                'period' => $entry->period->name,
                'starts_at' => substr($entry->period->starts_at, 0, 5),
                'ends_at' => substr($entry->period->ends_at, 0, 5),
                'subject' => $entry->subject->name,
                'color' => $entry->subject->color,
                'section' => $this->sectionLabel($entry, $childrenBySection, $user),
                'room' => $entry->room ?? $entry->section->room,
            ])
            ->all();

        return array_values($lessons);
    }

    /**
     * Guardians with several children see whose lesson it is.
     *
     * @param  array<int, list<string>>  $childrenBySection
     */
    private function sectionLabel(TimetableEntry $entry, array $childrenBySection, User $user): string
    {
        $children = $childrenBySection[$entry->section_id] ?? [];

        if ($children === [] || $user->student()->exists()) {
            return $entry->section->fullName();
        }

        return implode(', ', $children).' · '.$entry->section->fullName();
    }
}
