<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's pay for a payroll run; amounts are encrypted at rest and in cents.
 *
 * @property int $id
 * @property int $school_id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property array{basic: int, allowances: list<array{name: string, amount: int}>, deductions: list<array{name: string, amount: int, unpaid_leave?: bool}>} $breakdown
 * @property int $gross
 * @property int $deductions
 * @property int $net
 * @property int $unpaid_leave_days
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'payroll_run_id', 'employee_id', 'breakdown', 'gross', 'deductions', 'net', 'unpaid_leave_days'])]
class Payslip extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'breakdown' => 'encrypted:array',
            'gross' => 'encrypted',
            'deductions' => 'encrypted',
            'net' => 'encrypted',
            'unpaid_leave_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
