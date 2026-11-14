<?php

namespace App\Http\Controllers\Academics;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Section;
use App\Models\TimetableEntry;
use App\Services\Academics\TeacherDirectory;
use App\Services\People\PortalScope;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Timetable grid, viewed per section (editable) or per teacher (read-only).
 */
class TimetableController extends Controller
{
    public function __construct(
        private TeacherDirectory $teachers,
        private SettingsService $settings,
        private CurrentSchool $currentSchool,
        private PortalScope $portalScope,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('timetables.view');

        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'view' => ['nullable', 'in:section,teacher'],
        ]);

        $user = $request->user();
        $years = AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']);
        $year = $years->firstWhere('id', $filters['academic_year_id'] ?? null)
            ?? $years->firstWhere('is_current', true)
            ?? $years->first();

        $sections = Section::query()
            ->with('schoolClass:id,name,level')
            ->get()
            ->sortBy(fn (Section $section): string => sprintf('%05d %s %s', $section->schoolClass->level, $section->schoolClass->name, $section->name))
            ->values();

        $teachers = $this->teachers->forSchool((int) $this->currentSchool->id());
        $isTeacher = $teachers->contains('id', $user->id);

        // Students and guardians only see their own (or their children's) sections.
        $allowedSectionIds = $this->portalScope->sectionIds($user, $year);

        if ($allowedSectionIds !== null) {
            $sections = $sections->whereIn('id', $allowedSectionIds)->values();
            $teachers = collect();
            $filters['view'] = 'section';
        }

        $view = $filters['view'] ?? ($isTeacher && ! $user->can('timetables.edit') ? 'teacher' : 'section');
        $section = $sections->firstWhere('id', $filters['section_id'] ?? null) ?? $sections->first();
        $teacherId = $filters['teacher_id'] ?? ($isTeacher ? $user->id : $teachers->first()?->id);

        $entries = $year === null ? collect() : TimetableEntry::query()
            ->with(['subject:id,name,code,color', 'teacher:id,name', 'section.schoolClass:id,name'])
            ->where('academic_year_id', $year->id)
            ->when($view === 'section', fn ($query) => $query->where('section_id', $section->id ?? 0))
            ->when($view === 'teacher', fn ($query) => $query->where('teacher_id', $teacherId ?? 0))
            ->get();

        return Inertia::render('academics/timetable/index', [
            'years' => $years,
            'sections' => $sections->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->fullName(),
                'room' => $section->room,
            ]),
            'teachers' => $teachers,
            'periods' => Period::query()->orderBy('position')->get()->map(fn (Period $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_at' => substr($period->starts_at, 0, 5),
                'ends_at' => substr($period->ends_at, 0, 5),
                'is_break' => $period->is_break,
            ]),
            'days' => array_map(
                fn (Weekday $day): array => ['value' => $day->value, 'label' => $day->label()],
                Weekday::forSchoolWeek((string) $this->settings->current('academic.school_week')),
            ),
            'selected' => [
                'view' => $view,
                'academic_year_id' => $year?->id,
                'section_id' => $section?->id,
                'teacher_id' => $teacherId,
            ],
            'entries' => $entries->map(fn (TimetableEntry $entry): array => [
                'id' => $entry->id,
                'day_of_week' => $entry->day_of_week->value,
                'period_id' => $entry->period_id,
                'room' => $entry->room,
                'subject' => $entry->subject->only(['id', 'name', 'code', 'color']),
                'teacher' => $entry->teacher?->only(['id', 'name']),
                'section' => ['id' => $entry->section_id, 'name' => $entry->section->fullName()],
            ])->values(),
            'subjects' => $view === 'section' && $section !== null
                ? $this->subjectAllocation($section, $entries)
                : [],
            'canEdit' => $view === 'section' && $year !== null && $user->can('timetables.edit') && $user->can('timetables.create'),
        ]);
    }

    /**
     * Subjects of the section's class with planned vs scheduled periods per week.
     *
     * @param  Collection<int, TimetableEntry>  $entries
     * @return list<array{id: int, name: string, code: string, color: string|null, planned: int, scheduled: int}>
     */
    private function subjectAllocation(Section $section, $entries): array
    {
        $scheduled = $entries->countBy('subject_id');

        $allocation = $section->schoolClass->subjects()->orderBy('name')->get()->map(fn ($subject): array => [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code,
            'color' => $subject->color,
            'planned' => (int) $subject->getRelationValue('pivot')?->getAttribute('periods_per_week'),
            'scheduled' => (int) $scheduled->get($subject->id, 0),
        ])->all();

        return array_values($allocation);
    }
}
