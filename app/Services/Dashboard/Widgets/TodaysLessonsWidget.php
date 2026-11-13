<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\AcademicYear;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\Dashboard\DashboardWidget;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;

/**
 * The signed-in teacher's lessons for today from the current academic year.
 */
class TodaysLessonsWidget implements DashboardWidget
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'todaysLessons';
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->currentSchool->id() !== null
            && $user->hasPermission('timetables.view')
            && TimetableEntry::query()->where('teacher_id', $user->id)->exists();
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

        $lessons = TimetableEntry::query()
            ->with(['period', 'subject:id,name,color', 'section.schoolClass:id,name'])
            ->where('academic_year_id', $year->id)
            ->where('teacher_id', $user->id)
            ->where('day_of_week', $today)
            ->get()
            ->sortBy(fn (TimetableEntry $entry): int => $entry->period->position)
            ->map(fn (TimetableEntry $entry): array => [
                'period' => $entry->period->name,
                'starts_at' => substr($entry->period->starts_at, 0, 5),
                'ends_at' => substr($entry->period->ends_at, 0, 5),
                'subject' => $entry->subject->name,
                'color' => $entry->subject->color,
                'section' => $entry->section->fullName(),
                'room' => $entry->room ?? $entry->section->room,
            ])
            ->all();

        return array_values($lessons);
    }
}
