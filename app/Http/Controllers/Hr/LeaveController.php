<?php

namespace App\Http\Controllers\Hr;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\LeaveService;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff apply for leave against their yearly allowances; HR (leaves.approve) reviews.
 */
class LeaveController extends Controller
{
    use ValidatesSchoolRecords;

    public function __construct(
        private LeaveService $leave,
        private CurrentSchool $currentSchool,
    ) {}

    /**
     * My leave: balances, request form and history.
     */
    public function mine(Request $request): Response
    {
        $this->leave->ensureTypes((int) $this->currentSchool->id());
        $employee = $request->user()->employee;
        $year = (int) now()->year;

        return Inertia::render('hr/leave/mine', [
            'employee' => $employee?->only(['id', 'first_name', 'last_name']),
            'balances' => $employee === null ? [] : LeaveType::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (LeaveType $type): array => ['id' => $type->id, 'name' => $type->name, 'is_paid' => $type->is_paid, ...$this->leave->balanceFor($employee, $type, $year)])
                ->values(),
            'requests' => $employee === null ? [] : $employee->leaveRequests()->with('leaveType:id,name')->orderByDesc('starts_on')->limit(50)->get()
                ->map(fn (LeaveRequest $leave): array => $this->present($leave))
                ->values(),
            'canRequest' => $employee !== null && $request->user()->can('leaves.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('leaves.create');
        $employee = $request->user()->employee ?? abort(403, __('Your account is not linked to a staff record.'));

        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', $this->existsInSchool('leave_types')->where('is_active', true)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->leave->request(
            $employee,
            LeaveType::query()->findOrFail((int) $validated['leave_type_id']),
            CarbonImmutable::parse($validated['starts_on']),
            CarbonImmutable::parse($validated['ends_on']),
            $validated['reason'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave request submitted.')]);

        return to_route('hr.leave.mine');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->employee->user_id === $request->user()->id, 403);

        $this->leave->cancel($leaveRequest);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave request cancelled.')]);

        return to_route('hr.leave.mine');
    }

    /**
     * All requests, for HR to review.
     */
    public function requests(Request $request): Response
    {
        Gate::authorize('leaves.approve');
        $this->leave->ensureTypes((int) $this->currentSchool->id());

        $filters = $request->validate(['status' => ['nullable', Rule::enum(LeaveStatus::class)]]);
        $status = $filters['status'] ?? LeaveStatus::Pending->value;

        $requests = LeaveRequest::query()
            ->with(['employee:id,first_name,last_name,designation,user_id', 'leaveType:id,name', 'reviewer:id,name'])
            ->where('status', $status)
            ->orderBy('starts_on', $status === LeaveStatus::Pending->value ? 'asc' : 'desc')
            ->limit(100)
            ->get();

        return Inertia::render('hr/leave/requests', [
            'requests' => $requests->map(fn (LeaveRequest $leave): array => [
                ...$this->present($leave),
                'employee' => $leave->employee->full_name,
                'designation' => $leave->employee->designation,
                'own' => $leave->employee->user_id === $request->user()->id,
            ])->values(),
            'status' => $status,
            'statuses' => LeaveStatus::options(),
            'types' => LeaveType::query()->orderBy('name')->get(['id', 'name', 'days_per_year', 'is_paid', 'is_active']),
        ]);
    }

    public function review(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        Gate::authorize('leaves.approve');

        $validated = $request->validate([
            'decision' => ['required', Rule::in([LeaveStatus::Approved->value, LeaveStatus::Rejected->value])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->leave->review($request->user(), $leaveRequest, LeaveStatus::from($validated['decision']), $validated['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave :status.', ['status' => strtolower(LeaveStatus::from($validated['decision'])->label())])]);

        return to_route('hr.leave.requests');
    }

    public function saveType(Request $request, ?LeaveType $leaveType = null): RedirectResponse
    {
        Gate::authorize('leaves.approve');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', $this->uniqueInSchool('leave_types', 'name', $leaveType?->id)],
            'days_per_year' => ['required', 'integer', 'min:0', 'max:366'],
            'is_paid' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $leaveType === null
            ? LeaveType::query()->create([...$validated, 'school_id' => $this->currentSchool->id()])
            : $leaveType->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave type saved.')]);

        return to_route('hr.leave.requests');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LeaveRequest $leave): array
    {
        return [
            'id' => $leave->id,
            'type' => $leave->leaveType->name,
            'starts_on' => $leave->starts_on->toDateString(),
            'ends_on' => $leave->ends_on->toDateString(),
            'days' => $leave->days,
            'reason' => $leave->reason,
            'status' => $leave->status->value,
            'status_label' => $leave->status->label(),
            'review_note' => $leave->review_note,
            'reviewer' => $leave->relationLoaded('reviewer') ? $leave->reviewer?->name : null,
            'cancellable' => $leave->status === LeaveStatus::Pending || ($leave->status === LeaveStatus::Approved && $leave->starts_on->isFuture()),
        ];
    }
}
