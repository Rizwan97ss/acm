<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\PayrollStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payroll for one month: draft → approved → paid. Totals are in cents.
 *
 * @property int $id
 * @property int $school_id
 * @property string $period
 * @property PayrollStatus $status
 * @property int $total_gross
 * @property int $total_deductions
 * @property int $total_net
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $paid_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'period', 'total_gross', 'total_deductions', 'total_net', 'created_by'])]
class PayrollRun extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollStatus::class,
            'total_gross' => 'integer',
            'total_deductions' => 'integer',
            'total_net' => 'integer',
            'approved_at' => 'datetime',
            'paid_on' => 'date',
        ];
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
