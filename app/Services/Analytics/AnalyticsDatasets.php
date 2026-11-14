<?php

namespace App\Services\Analytics;

use App\Enums\InvoiceStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Mark;
use App\Models\Payment;
use Carbon\CarbonImmutable;

/**
 * Builds the small, aggregated datasets sent to the analytics service. All queries go
 * through Eloquent so the current-school global scope applies; nothing identifying
 * beyond internal IDs and section labels leaves Laravel (names are attached afterwards).
 */
class AnalyticsDatasets
{
    private const string ATTENDED_SQL = "sum(case when attendance_records.status = 'present' then 1 else 0 end) as present, sum(case when attendance_records.status = 'absent' then 1 else 0 end) as absent, sum(case when attendance_records.status = 'late' then 1 else 0 end) as late, sum(case when attendance_records.status = 'excused' then 1 else 0 end) as excused";

    /**
     * School-wide attendance counts per day.
     *
     * @return array{days: list<array{date: string, present: int, absent: int, late: int, excused: int}>}
     */
    public function attendanceDays(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = AttendanceRecord::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->toBase()
            ->selectRaw('date, '.self::ATTENDED_SQL)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return ['days' => array_values($rows->map(fn (object $row): array => [
            'date' => substr((string) $row->date, 0, 10),
            'present' => (int) $row->present,
            'absent' => (int) $row->absent,
            'late' => (int) $row->late,
            'excused' => (int) $row->excused,
        ])->all())];
    }

    /**
     * Per-student attendance for the academic year, plus absences in the last 14 days.
     *
     * @return array{students: list<array{student_id: int, section: string, present: int, absent: int, late: int, excused: int, recent_absences: int}>, threshold: float}
     */
    public function studentAttendance(AcademicYear $year, float $threshold): array
    {
        $recentFrom = now()->subDays(14)->toDateString();

        $rows = AttendanceRecord::query()
            ->where('attendance_records.academic_year_id', $year->id)
            ->join('sections', 'sections.id', '=', 'attendance_records.section_id')
            ->join('school_classes', 'school_classes.id', '=', 'sections.school_class_id')
            ->toBase()
            ->selectRaw('attendance_records.student_id, max(school_classes.name) as class_name, max(sections.name) as section_name, '.self::ATTENDED_SQL)
            ->selectRaw("sum(case when attendance_records.status = 'absent' and attendance_records.date >= ? then 1 else 0 end) as recent_absences", [$recentFrom])
            ->groupBy('attendance_records.student_id')
            ->get();

        return [
            'students' => array_values($rows->map(fn (object $row): array => [
                'student_id' => (int) $row->student_id,
                'section' => "{$row->class_name} {$row->section_name}",
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'late' => (int) $row->late,
                'excused' => (int) $row->excused,
                'recent_absences' => (int) $row->recent_absences,
            ])->all()),
            'threshold' => $threshold,
        ];
    }

    /**
     * Scores from the two most recent published exams of the year, oldest first, and each
     * student's attendance rate for the correlation.
     *
     * @return array{exams: list<array{exam_id: int, exam: string, scores: list<array{student_id: int, subject: string, percentage: float, passed: bool}>}>, attendance: object}
     */
    public function performance(AcademicYear $year): array
    {
        $exams = Exam::query()
            ->where('academic_year_id', $year->id)
            ->whereNotNull('published_at')
            ->orderByDesc('starts_on')
            ->limit(2)
            ->get(['id', 'name'])
            ->reverse()
            ->values();

        $payload = [];

        foreach ($exams as $exam) {
            $marks = Mark::query()
                ->join('exam_papers', 'exam_papers.id', '=', 'marks.exam_paper_id')
                ->join('subjects', 'subjects.id', '=', 'exam_papers.subject_id')
                ->where('exam_papers.exam_id', $exam->id)
                ->where(fn ($query) => $query->whereNotNull('marks.marks_obtained')->orWhere('marks.is_absent', true))
                ->toBase()
                ->get(['marks.student_id', 'marks.marks_obtained', 'marks.is_absent', 'exam_papers.max_marks', 'exam_papers.pass_marks', 'subjects.name as subject']);

            $payload[] = [
                'exam_id' => $exam->id,
                'exam' => $exam->name,
                'scores' => array_values($marks->map(function (object $mark): array {
                    $obtained = $mark->is_absent ? 0.0 : (float) $mark->marks_obtained;
                    $max = max(0.01, (float) $mark->max_marks);

                    return [
                        'student_id' => (int) $mark->student_id,
                        'subject' => (string) $mark->subject,
                        'percentage' => round(min(100, $obtained / $max * 100), 2),
                        'passed' => ! $mark->is_absent && $obtained >= (float) $mark->pass_marks,
                    ];
                })->all()),
            ];
        }

        $rates = [];

        foreach ($this->studentAttendance($year, 0)['students'] as $student) {
            $countable = $student['present'] + $student['late'] + $student['absent'];

            if ($countable > 0) {
                $rates[(string) $student['student_id']] = round(($student['present'] + $student['late']) / $countable * 100, 2);
            }
        }

        return ['exams' => $payload, 'attendance' => (object) $rates];
    }

    /**
     * Monthly billed / collected / approved-expense totals and the open receivables.
     *
     * @return array{months: list<array{month: string, billed: int, collected: int, expenses: int}>, open_invoices: list<array{balance: int, days_overdue: int}>, horizon: int}
     */
    public function finance(int $months = 12, int $horizon = 3): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $totals = [];

        for ($i = 0; $i < $months; $i++) {
            $totals[$start->addMonths($i)->format('Y-m')] = ['billed' => 0, 'collected' => 0, 'expenses' => 0];
        }

        $accumulate = function (iterable $rows, string $key) use (&$totals): void {
            foreach ($rows as $row) {
                $month = substr((string) $row->day, 0, 7);

                if (isset($totals[$month])) {
                    $totals[$month][$key] += (int) $row->amount;
                }
            }
        };

        $since = $start->toDateString();

        $accumulate(Invoice::query()->where('status', '!=', InvoiceStatus::Void->value)->whereDate('issued_on', '>=', $since)->toBase()->selectRaw('issued_on as day, sum(total) as amount')->groupBy('issued_on')->get(), 'billed');
        $accumulate(Payment::query()->whereNull('reversed_at')->whereDate('paid_on', '>=', $since)->toBase()->selectRaw('paid_on as day, sum(amount) as amount')->groupBy('paid_on')->get(), 'collected');
        $accumulate(Expense::query()->where('status', 'approved')->whereDate('spent_on', '>=', $since)->toBase()->selectRaw('spent_on as day, sum(amount) as amount')->groupBy('spent_on')->get(), 'expenses');

        $today = CarbonImmutable::today();
        $open = array_values(Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->toBase()
            ->get(['total', 'paid', 'due_on'])
            ->map(fn (object $invoice): array => [
                'balance' => max(0, (int) $invoice->total - (int) $invoice->paid),
                'days_overdue' => (int) CarbonImmutable::parse($invoice->due_on)->startOfDay()->diffInDays($today, false),
            ])
            ->all());

        $monthly = [];

        foreach ($totals as $month => $values) {
            $monthly[] = ['month' => (string) $month, 'billed' => $values['billed'], 'collected' => $values['collected'], 'expenses' => $values['expenses']];
        }

        return [
            'months' => $monthly,
            'open_invoices' => $open,
            'horizon' => $horizon,
        ];
    }
}
