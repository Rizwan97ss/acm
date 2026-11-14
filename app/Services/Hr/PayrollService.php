<?php

namespace App\Services\Hr;

use App\Enums\EmployeeStatus;
use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryProfile;
use App\Models\User;
use App\Services\Finance\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly payroll: draft (recalculable) → approved → paid. Payment posts
 * Dr Salaries (earned pay) / Cr Bank (net pay) / Cr Payroll deductions payable.
 * Unpaid leave reduces earned pay rather than being a payable deduction.
 */
class PayrollService
{
    public function __construct(
        private LeaveService $leave,
        private LedgerService $ledger,
    ) {}

    /**
     * @param  list<array{name: string, amount: int}>  $allowances
     * @param  list<array{name: string, amount: int}>  $deductions
     */
    public function saveProfile(Employee $employee, int $basic, array $allowances, array $deductions): SalaryProfile
    {
        return SalaryProfile::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['school_id' => $employee->school_id, 'basic' => (string) $basic, 'allowances' => $allowances, 'deductions' => $deductions],
        );
    }

    /**
     * Create the run for a month, or recalculate it while it is still a draft.
     */
    public function generate(User $actor, int $schoolId, string $period): PayrollRun
    {
        $run = PayrollRun::query()->firstOrNew(['period' => $period]);

        if ($run->exists && $run->status !== PayrollStatus::Draft) {
            throw ValidationException::withMessages(['period' => __('Payroll for :period is already :status.', ['period' => $period, 'status' => strtolower($run->status->label())])]);
        }

        $monthStart = CarbonImmutable::createFromFormat('Y-m-d', "{$period}-01")->startOfDay();
        $workingDays = max(1, $this->leave->workingDays($monthStart, $monthStart->endOfMonth()));

        return DB::transaction(function () use ($actor, $schoolId, $run, $monthStart, $workingDays): PayrollRun {
            $run->fill(['school_id' => $schoolId, 'created_by' => $actor->id]);
            $run->forceFill(['status' => PayrollStatus::Draft])->save();
            $run->payslips()->delete();

            $employees = Employee::query()
                ->with('salaryProfile')
                ->where('status', EmployeeStatus::Active)
                ->whereDate('joined_on', '<=', $monthStart->endOfMonth()->toDateString())
                ->whereHas('salaryProfile')
                ->get();

            $totals = ['gross' => 0, 'deductions' => 0, 'net' => 0];

            foreach ($employees as $employee) {
                $profile = $employee->salaryProfile;
                $basic = (int) $profile->basic;
                $allowances = $profile->allowances;
                $deductions = $profile->deductions;

                $unpaidDays = $this->leave->unpaidDaysIn($employee, $monthStart);

                if ($unpaidDays > 0) {
                    $deductions[] = ['name' => "Unpaid leave ({$unpaidDays} days)", 'amount' => (int) round($basic / $workingDays * $unpaidDays), 'unpaid_leave' => true];
                }

                $gross = $basic + array_sum(array_column($allowances, 'amount'));
                $deducted = min($gross, array_sum(array_column($deductions, 'amount')));
                $net = $gross - $deducted;

                Payslip::query()->create([
                    'school_id' => $schoolId,
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'breakdown' => ['basic' => $basic, 'allowances' => $allowances, 'deductions' => $deductions],
                    'gross' => (string) $gross,
                    'deductions' => (string) $deducted,
                    'net' => (string) $net,
                    'unpaid_leave_days' => $unpaidDays,
                ]);

                $totals['gross'] += $gross;
                $totals['deductions'] += $deducted;
                $totals['net'] += $net;
            }

            $run->forceFill(['total_gross' => $totals['gross'], 'total_deductions' => $totals['deductions'], 'total_net' => $totals['net']])->save();

            return $run;
        });
    }

    public function approve(User $actor, PayrollRun $run): void
    {
        if ($run->status !== PayrollStatus::Draft || ! $run->payslips()->exists()) {
            throw ValidationException::withMessages(['run' => __('Only a draft payroll with payslips can be approved.')]);
        }

        $run->forceFill(['status' => PayrollStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
    }

    public function markPaid(User $actor, PayrollRun $run, CarbonImmutable $paidOn): void
    {
        if ($run->status !== PayrollStatus::Approved) {
            throw ValidationException::withMessages(['run' => __('Approve the payroll before paying it.')]);
        }

        $unpaidLeave = $run->payslips->sum(fn (Payslip $payslip): int => (int) array_sum(array_column(
            array_filter($payslip->breakdown['deductions'], fn (array $deduction): bool => ! empty($deduction['unpaid_leave'])),
            'amount',
        )));

        $earned = $run->total_gross - $unpaidLeave;
        $payable = $run->total_deductions - $unpaidLeave;

        DB::transaction(function () use ($actor, $run, $paidOn, $earned, $payable): void {
            $run->forceFill(['status' => PayrollStatus::Paid, 'paid_on' => $paidOn->toDateString()])->save();

            if ($earned > 0) {
                $this->ledger->post($run->school_id, $paidOn, "Payroll {$run->period}", $run, [
                    [LedgerService::SALARIES, $earned, 0],
                    [LedgerService::BANK, 0, $run->total_net],
                    [LedgerService::PAYROLL_PAYABLE, 0, $payable],
                ], $actor);
            }
        });
    }
}
