<?php

namespace App\Services\Academics;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentAbsentNotification;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Active enrollments of the section for the year, ordered by roll number.
     *
     * @return Collection<int, Enrollment>
     */
    public function roster(Section $section, AcademicYear $year): Collection
    {
        return Enrollment::query()
            ->with('student:id,first_name,last_name,admission_no')
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active)
            ->orderByRaw('roll_number is null')
            ->orderBy('roll_number')
            ->get();
    }

    /**
     * Save a day's attendance for a section and alert guardians of newly absent students.
     *
     * @param  array<int, array{status: string, remarks: string|null}>  $entries  keyed by student id
     * @return array{saved: int, absent: int}
     */
    public function record(User $marker, Section $section, AcademicYear $year, CarbonImmutable $date, array $entries): array
    {
        $enrolledIds = $this->roster($section, $year)->pluck('student_id')->all();
        $unknown = array_diff(array_keys($entries), $enrolledIds);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['entries' => __('Some students are not enrolled in this section.')]);
        }

        $newlyAbsent = [];

        DB::transaction(function () use ($marker, $section, $year, $date, $entries, &$newlyAbsent): void {
            $existing = AttendanceRecord::query()
                ->whereDate('date', $date->toDateString())
                ->whereIn('student_id', array_keys($entries))
                ->get()
                ->keyBy('student_id');

            foreach ($entries as $studentId => $entry) {
                $status = AttendanceStatus::from($entry['status']);
                $record = $existing->get($studentId) ?? new AttendanceRecord([
                    'school_id' => $section->school_id,
                    'student_id' => $studentId,
                    'date' => $date->toDateString(),
                ]);

                if ($status === AttendanceStatus::Absent && $record->status !== AttendanceStatus::Absent) {
                    $newlyAbsent[] = $studentId;
                }

                $record->fill([
                    'academic_year_id' => $year->id,
                    'section_id' => $section->id,
                    'status' => $status,
                    'remarks' => $entry['remarks'],
                    'marked_by' => $marker->id,
                ])->save();
            }
        });

        $this->notifyGuardians($newlyAbsent, $date);

        return [
            'saved' => count($entries),
            'absent' => count(array_filter($entries, fn (array $entry): bool => $entry['status'] === AttendanceStatus::Absent->value)),
        ];
    }

    /**
     * Attendance totals for a student in a year.
     *
     * @return array{present: int, absent: int, late: int, excused: int, total: int, percentage: float|null}
     */
    public function summary(Student $student, AcademicYear $year): array
    {
        $counts = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (AttendanceStatus $status): int => (int) ($counts[$status->value] ?? 0);
        $attended = $count(AttendanceStatus::Present) + $count(AttendanceStatus::Late);
        $countable = $attended + $count(AttendanceStatus::Absent);

        return [
            'present' => $count(AttendanceStatus::Present),
            'absent' => $count(AttendanceStatus::Absent),
            'late' => $count(AttendanceStatus::Late),
            'excused' => $count(AttendanceStatus::Excused),
            'total' => $countable + $count(AttendanceStatus::Excused),
            'percentage' => $countable > 0 ? round($attended / $countable * 100, 1) : null,
        ];
    }

    /**
     * Staff may record today and the previous week; supervisors may correct any past day.
     */
    public function canRecordOn(CarbonImmutable $date, bool $supervisor): bool
    {
        $today = CarbonImmutable::now((string) $this->settings->current('localization.timezone'))->startOfDay();

        if ($date->greaterThan($today)) {
            return false;
        }

        return $supervisor || $date->greaterThanOrEqualTo($today->subDays(7));
    }

    /**
     * @param  list<int>  $studentIds
     */
    private function notifyGuardians(array $studentIds, CarbonImmutable $date): void
    {
        if ($studentIds === [] || ! $this->settings->current('notifications.notify_guardians_on_absence') || ! $this->settings->current('notifications.email_enabled')) {
            return;
        }

        Student::query()
            ->with(['guardians' => fn ($query) => $query->wherePivot('is_emergency_contact', true)->whereNotNull('email')])
            ->whereIn('id', $studentIds)
            ->get()
            ->each(function (Student $student) use ($date): void {
                if ($student->guardians->isNotEmpty()) {
                    Notification::send($student->guardians, new StudentAbsentNotification($student, $date));
                }
            });
    }
}
