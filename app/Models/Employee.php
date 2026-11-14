<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Concerns\HasBlindIndexes;
use App\Concerns\HasFullName;
use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff member. Teaching staff (`is_teaching`) are managed with teachers.* permissions,
 * everyone else with employees.*.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $user_id
 * @property string $employee_no
 * @property string $first_name
 * @property string $last_name
 * @property-read string $full_name
 * @property Gender|null $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $phone_index
 * @property string|null $national_id
 * @property string|null $national_id_index
 * @property string|null $bank_account
 * @property string|null $address
 * @property bool $is_teaching
 * @property string $designation
 * @property string|null $department
 * @property string|null $qualification
 * @property EmploymentType $employment_type
 * @property CarbonImmutable $joined_on
 * @property CarbonImmutable|null $left_on
 * @property EmployeeStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['school_id', 'user_id', 'employee_no', 'first_name', 'last_name', 'gender', 'date_of_birth', 'email', 'phone', 'national_id', 'bank_account', 'address', 'is_teaching', 'designation', 'department', 'qualification', 'employment_type', 'joined_on', 'left_on', 'status'])]
#[Hidden(['phone_index', 'national_id_index'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, BelongsToSchool, HasBlindIndexes, HasFactory, HasFullName, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'employment_type' => 'full_time',
        'is_teaching' => false,
    ];

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['phone_index', 'national_id_index'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'phone' => 'encrypted',
            'national_id' => 'encrypted',
            'bank_account' => 'encrypted',
            'address' => 'encrypted',
            'is_teaching' => 'boolean',
            'employment_type' => EmploymentType::class,
            'joined_on' => 'date',
            'left_on' => 'date',
            'status' => EmployeeStatus::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function blindIndexes(): array
    {
        return ['phone' => 'phone_index', 'national_id' => 'national_id_index'];
    }

    protected function normalizeForBlindIndex(string $attribute, string $value): string
    {
        return $attribute === 'phone'
            ? (string) preg_replace('/\D+/', '', $value)
            : (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($value));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
