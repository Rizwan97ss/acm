<?php

namespace App\Http\Controllers\Academics;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\AttendanceRequest;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
use App\Services\Academics\AcademicOptions;
use App\Services\Academics\AttendanceService;
use App\Services\Academics\TeachingScope;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily roll call per section, and a monthly register.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private TeachingScope $scope,
        private AcademicOptions $options,
        private SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('attendance.view');

        $filters = $request->validate([
            'section_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $year = AcademicYear::query()->current()->first();
        $sections = $this->accessibleSections($request, $year);
        $section = $sections->firstWhere('id', $filters['section_id'] ?? null) ?? $sections->first();
        $date = isset($filters['date']) ? CarbonImmutable::parse($filters['date']) : $this->today();
        $supervisor = $this->scope->supervises($request->user(), 'attendance');

        $rows = [];

        if ($year !== null && $section !== null) {
            $sectionModel = Section::query()->findOrFail($section['id']);
            $records = AttendanceRecord::query()
                ->where('section_id', $sectionModel->id)
                ->whereDate('date', $date->toDateString())
                ->get()
                ->keyBy('student_id');

            $rows = $this->attendance->roster($sectionModel, $year)->map(fn (Enrollment $enrollment): array => [
                'student_id' => $enrollment->student_id,
                'name' => $enrollment->student->full_name,
                'admission_no' => $enrollment->student->admission_no,
                'roll_number' => $enrollment->roll_number,
                'status' => $records->get($enrollment->student_id)?->status->value,
                'remarks' => $records->get($enrollment->student_id)?->remarks,
            ])->values()->all();
        }

        return Inertia::render('academics/attendance/index', [
            'year' => $year?->only(['id', 'name']),
            'sections' => $sections,
            'selected' => ['section_id' => $section['id'] ?? null, 'date' => $date->toDateString()],
            'rows' => $rows,
            'statuses' => AttendanceStatus::options(),
            'canRecord' => $year !== null && $section !== null
                && $request->user()->can('attendance.create')
                && $this->attendance->canRecordOn($date, $supervisor),
            'isRecorded' => collect($rows)->contains(fn (array $row): bool => $row['status'] !== null),
        ]);
    }

    public function store(AttendanceRequest $request): RedirectResponse
    {
        $year = AcademicYear::query()->current()->firstOrFail();
        $section = Section::query()->findOrFail($request->integer('section_id'));
        $date = CarbonImmutable::parse($request->string('date')->toString());

        abort_unless($this->scope->canAccessSection($request->user(), $section, $year, 'attendance'), 403);

        if (! $this->attendance->canRecordOn($date, $this->scope->supervises($request->user(), 'attendance'))) {
            throw ValidationException::withMessages(['date' => __('Attendance can only be recorded for today and the past week.')]);
        }

        $result = $this->attendance->record($request->user(), $section, $year, $date, $request->entries());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance saved: :absent absent of :total.', ['absent' => $result['absent'], 'total' => $result['saved']])]);

        return to_route('academics.attendance.index', ['section_id' => $section->id, 'date' => $date->toDateString()]);
    }

    /**
     * Students × days grid for one month.
     */
    public function register(Request $request): Response
    {
        Gate::authorize('attendance.view');

        $filters = $request->validate([
            'section_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $year = AcademicYear::query()->current()->first();
        $sections = $this->accessibleSections($request, $year);
        $section = $sections->firstWhere('id', $filters['section_id'] ?? null) ?? $sections->first();
        $month = CarbonImmutable::parse(($filters['month'] ?? $this->today()->format('Y-m')).'-01');
        $days = [];

        foreach (CarbonPeriod::create($month, $month->endOfMonth()) as $day) {
            $days[] = $day->format('Y-m-d');
        }

        $students = [];

        if ($year !== null && $section !== null) {
            $sectionModel = Section::query()->findOrFail($section['id']);
            $records = AttendanceRecord::query()
                ->where('section_id', $sectionModel->id)
                ->whereDate('date', '>=', $month->toDateString())
                ->whereDate('date', '<=', $month->endOfMonth()->toDateString())
                ->get()
                ->groupBy('student_id');

            $students = $this->attendance->roster($sectionModel, $year)->map(function (Enrollment $enrollment) use ($records): array {
                $byDay = $records->get($enrollment->student_id, collect())
                    ->mapWithKeys(fn (AttendanceRecord $record): array => [$record->date->toDateString() => $record->status]);

                $attended = $byDay->filter(fn (AttendanceStatus $status): bool => $status->countsAsPresent())->count();
                $countable = $byDay->filter(fn (AttendanceStatus $status): bool => $status !== AttendanceStatus::Excused)->count();

                return [
                    'student_id' => $enrollment->student_id,
                    'name' => $enrollment->student->full_name,
                    'roll_number' => $enrollment->roll_number,
                    'days' => $byDay->map(fn (AttendanceStatus $status): string => $status->value)->all(),
                    'percentage' => $countable > 0 ? round($attended / $countable * 100, 1) : null,
                ];
            })->values()->all();
        }

        return Inertia::render('academics/attendance/register', [
            'sections' => $sections,
            'selected' => ['section_id' => $section['id'] ?? null, 'month' => $month->format('Y-m')],
            'days' => $days,
            'students' => $students,
        ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string, school_class_id: int, room: string|null, capacity: int|null}>
     */
    private function accessibleSections(Request $request, ?AcademicYear $year): Collection
    {
        if ($year === null) {
            return collect();
        }

        $allowed = $this->scope->sectionIds($request->user(), $year, 'attendance');

        $sections = $this->options->sections();

        return ($allowed === null ? $sections : $sections->whereIn('id', $allowed))->values();
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) $this->settings->current('localization.timezone'))->startOfDay();
    }
}
