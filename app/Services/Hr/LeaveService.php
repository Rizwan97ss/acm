<?php

namespace App\Services\Hr;

use App\Enums\LeaveStatus;
use App\Enums\Weekday;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /**
     * @var list<array{name: string, days_per_year: int, is_paid: bool}>
     */
    private const array DEFAULT_TYPES = [
        ['name' => 'Annual leave', 'days_per_year' => 14, 'is_paid' => true],
        ['name' => 'Sick leave', 'days_per_year' => 10, 'is_paid' => true],
        ['name' => 'Casual leave', 'days_per_year' => 6, 'is_paid' => true],
        ['name' => 'Unpaid leave', 'days_per_year' => 60, 'is_paid' => false],
    ];

    public function __construct(private SettingsService $settings) {}

    public function ensureTypes(int $schoolId): void
    {
        if (LeaveType::query()->exists()) {
            return;
        }

        foreach (self::DEFAULT_TYPES as $type) {
            LeaveType::query()->create([...$type, 'school_id' => $schoolId]);
        }
    }

    /**
     * School days between two dates (inclusive), following the school-week setting.
     */
    public function workingDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $schoolDays = array_map(fn (Weekday $day): int => $day->value, Weekday::forSchoolWeek((string) $this->settings->current('academic.school_week')));
        $count = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $count += in_array($day->isoWeekday(), $schoolDays, true) ? 1 : 0;
        }

        return $count;
    }

    public function request(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $to, string $reason): LeaveRequest
    {
        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['ends_on' => __('The leave must end on or after it starts.')]);
        }

        $days = $this->workingDays($from, $to);

        if ($days === 0) {
            throw ValidationException::withMessages(['starts_on' => __('The selected dates contain no working days.')]);
        }

        $overlaps = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => __('These dates overlap another leave request.')]);
        }

        $remaining = $this->balanceFor($employee, $type, $from->year)['remaining'];

        if ($days > $remaining) {
            throw ValidationException::withMessages(['ends_on' => __('Only :days days of :type remain this year.', ['days' => $remaining, 'type' => $type->name])]);
        }

        $leave = new LeaveRequest([
            'school_id' => $employee->school_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => $from->toDateString(),
            'ends_on' => $to->toDateString(),
            'days' => $days,
            'reason' => $reason,
        ]);
        $leave->forceFill(['status' => LeaveStatus::Pending])->save();

        return $leave;
    }

    public function review(User $reviewer, LeaveRequest $leave, LeaveStatus $decision, ?string $note): void
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages(['decision' => __('Only pending requests can be reviewed.')]);
        }

        if ($leave->employee->user_id === $reviewer->id && ! $reviewer->isSuperAdmin()) {
            throw ValidationException::withMessages(['decision' => __('You cannot review your own leave request.')]);
        }

        $leave->forceFill(['status' => $decision, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
    }

    public function cancel(LeaveRequest $leave): void
    {
        $cancellable = $leave->status === LeaveStatus::Pending
            || ($leave->status === LeaveStatus::Approved && $leave->starts_on->isFuture());

        if (! $cancellable) {
            throw ValidationException::withMessages(['leave' => __('This request can no longer be cancelled.')]);
        }

        $leave->forceFill(['status' => LeaveStatus::Cancelled])->save();
    }

    /**
     * Days of a leave type used (approved), pending and remaining in a calendar year.
     *
     * @return array{allowance: int, used: int, pending: int, remaining: int}
     */
    public function balanceFor(Employee $employee, LeaveType $type, int $year): array
    {
        $taken = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereYear('starts_on', $year)
            ->toBase()
            ->selectRaw('status, sum(days) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $used = (int) ($taken[LeaveStatus::Approved->value] ?? 0);
        $pending = (int) ($taken[LeaveStatus::Pending->value] ?? 0);

        return [
            'allowance' => $type->days_per_year,
            'used' => $used,
            'pending' => $pending,
            'remaining' => max(0, $type->days_per_year - $used - $pending),
        ];
    }

    /**
     * Approved unpaid-leave working days of an employee that fall inside a month.
     */
    public function unpaidDaysIn(Employee $employee, CarbonImmutable $monthStart): int
    {
        $monthEnd = $monthStart->endOfMonth();

        return (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved)
            ->whereHas('leaveType', fn ($query) => $query->where('is_paid', false))
            ->whereDate('starts_on', '<=', $monthEnd->toDateString())
            ->whereDate('ends_on', '>=', $monthStart->toDateString())
            ->get()
            ->sum(fn (LeaveRequest $leave): int => $this->workingDays(
                $leave->starts_on->max($monthStart),
                $leave->ends_on->min($monthEnd),
            ));
    }
}
