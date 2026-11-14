<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monthly pay for an employee, encrypted at rest. Amounts are in cents.
 *
 * @property int $id
 * @property int $school_id
 * @property int $employee_id
 * @property int $basic
 * @property list<array{name: string, amount: int}> $allowances
 * @property list<array{name: string, amount: int}> $deductions
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'employee_id', 'basic', 'allowances', 'deductions'])]
class SalaryProfile extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basic' => 'encrypted',
            'allowances' => 'encrypted:array',
            'deductions' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
