<?php

namespace App\Http\Controllers\Hr;

use App\Enums\EmployeeStatus;
use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Finance\Money;
use App\Services\Hr\PayrollService;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(
        private PayrollService $payroll,
        private CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('payroll.view');

        return Inertia::render('hr/payroll/index', [
            'runs' => PayrollRun::query()->withCount('payslips')->orderByDesc('period')->get()->map(fn (PayrollRun $run): array => [
                'id' => $run->id,
                'period' => $run->period,
                'status' => $run->status->value,
                'status_label' => $run->status->label(),
                'total_gross' => $run->total_gross,
                'total_deductions' => $run->total_deductions,
                'total_net' => $run->total_net,
                'payslips_count' => (int) $run->getAttribute('payslips_count'),
                'paid_on' => $run->paid_on?->toDateString(),
            ])->values(),
            'can' => ['create' => $request->user()->can('payroll.create'), 'editSalaries' => $request->user()->can('payroll.edit')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('payroll.create');

        $validated = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $run = $this->payroll->generate($request->user(), (int) $this->currentSchool->id(), $validated['period']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll for :period calculated.', ['period' => $run->period])]);

        return to_route('hr.payroll.show', $run);
    }

    public function show(Request $request, PayrollRun $run): Response
    {
        Gate::authorize('payroll.view');

        $run->load('payslips.employee:id,first_name,last_name,employee_no,designation');

        return Inertia::render('hr/payroll/show', [
            'run' => [
                'id' => $run->id,
                'period' => $run->period,
                'status' => $run->status->value,
                'status_label' => $run->status->label(),
                'total_gross' => $run->total_gross,
                'total_deductions' => $run->total_deductions,
                'total_net' => $run->total_net,
                'paid_on' => $run->paid_on?->toDateString(),
            ],
            'payslips' => $run->payslips->sortBy(fn (Payslip $payslip): string => $payslip->employee->last_name)->map(fn (Payslip $payslip): array => [
                'id' => $payslip->id,
                'employee' => $payslip->employee->full_name,
                'employee_no' => $payslip->employee->employee_no,
                'designation' => $payslip->employee->designation,
                'gross' => (int) $payslip->gross,
                'deductions' => (int) $payslip->deductions,
                'net' => (int) $payslip->net,
                'unpaid_leave_days' => $payslip->unpaid_leave_days,
            ])->values(),
            'can' => [
                'recalculate' => $request->user()->can('payroll.create') && $run->status === PayrollStatus::Draft,
                'approve' => $request->user()->can('payroll.approve') && $run->status === PayrollStatus::Draft,
                'pay' => $request->user()->can('payroll.approve') && $run->status === PayrollStatus::Approved,
            ],
        ]);
    }

    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        Gate::authorize('payroll.approve');

        $this->payroll->approve($request->user(), $run);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll approved.')]);

        return to_route('hr.payroll.show', $run);
    }

    public function pay(Request $request, PayrollRun $run): RedirectResponse
    {
        Gate::authorize('payroll.approve');

        $validated = $request->validate(['paid_on' => ['required', 'date']]);
        $this->payroll->markPaid($request->user(), $run->load('payslips'), CarbonImmutable::parse($validated['paid_on']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll marked as paid and posted to the ledger.')]);

        return to_route('hr.payroll.show', $run);
    }

    /**
     * Printable payslip, for payroll staff or the employee themselves once the run is approved.
     */
    public function payslip(Request $request, Payslip $payslip): Response
    {
        $payslip->load(['run', 'employee']);
        $isOwn = $payslip->employee->user_id === $request->user()->id;

        abort_unless($request->user()->can('payroll.view') || ($isOwn && $payslip->run->status !== PayrollStatus::Draft), 403);

        return Inertia::render('hr/payroll/payslip', [
            'payslip' => [
                'period' => $payslip->run->period,
                'status_label' => $payslip->run->status->label(),
                'employee' => [
                    'name' => $payslip->employee->full_name,
                    'employee_no' => $payslip->employee->employee_no,
                    'designation' => $payslip->employee->designation,
                    'department' => $payslip->employee->department,
                ],
                'breakdown' => $payslip->breakdown,
                'gross' => (int) $payslip->gross,
                'deductions' => (int) $payslip->deductions,
                'net' => (int) $payslip->net,
                'unpaid_leave_days' => $payslip->unpaid_leave_days,
            ],
        ]);
    }

    public function mine(Request $request): Response
    {
        $employee = $request->user()->employee;

        return Inertia::render('hr/payroll/mine', [
            'payslips' => $employee === null ? [] : Payslip::query()
                ->with('run:id,period,status')
                ->where('employee_id', $employee->id)
                ->whereHas('run', fn ($query) => $query->where('status', '!=', PayrollStatus::Draft))
                ->get()
                ->sortByDesc(fn (Payslip $payslip): string => $payslip->run->period)
                ->map(fn (Payslip $payslip): array => [
                    'id' => $payslip->id,
                    'period' => $payslip->run->period,
                    'net' => (int) $payslip->net,
                    'status_label' => $payslip->run->status->label(),
                ])->values(),
        ]);
    }

    public function salaries(Request $request): Response
    {
        Gate::authorize('payroll.edit');

        return Inertia::render('hr/payroll/salaries', [
            'employees' => Employee::query()
                ->with('salaryProfile')
                ->where('status', EmployeeStatus::Active)
                ->orderBy('last_name')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_no' => $employee->employee_no,
                    'designation' => $employee->designation,
                    'profile' => $employee->salaryProfile === null ? null : [
                        'basic' => (int) $employee->salaryProfile->basic,
                        'allowances' => $employee->salaryProfile->allowances,
                        'deductions' => $employee->salaryProfile->deductions,
                    ],
                ])->values(),
        ]);
    }

    public function saveSalary(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('payroll.edit');

        $validated = $request->validate([
            'basic' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'allowances' => ['present', 'array', 'max:15'],
            'allowances.*.name' => ['required', 'string', 'max:60'],
            'allowances.*.amount' => ['required', 'numeric', 'min:0'],
            'deductions' => ['present', 'array', 'max:15'],
            'deductions.*.name' => ['required', 'string', 'max:60'],
            'deductions.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $lines = fn (array $items): array => array_values(array_map(fn (array $item): array => [
            'name' => (string) $item['name'],
            'amount' => Money::toCents($item['amount']),
        ], $items));

        $this->payroll->saveProfile($employee, Money::toCents($validated['basic']), $lines($validated['allowances']), $lines($validated['deductions']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Salary saved for :name.', ['name' => $employee->full_name])]);

        return to_route('hr.payroll.salaries');
    }
}
