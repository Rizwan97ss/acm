<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Concerns\HasBlindIndexes;
use App\Concerns\HasFullName;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $school_id
 * @property int|null $user_id
 * @property string $admission_no
 * @property string $first_name
 * @property string $last_name
 * @property-read string $full_name
 * @property Gender $gender
 * @property CarbonImmutable $date_of_birth
 * @property string|null $email
 * @property string|null $national_id
 * @property string|null $national_id_index
 * @property string|null $blood_group
 * @property string|null $medical_notes
 * @property string|null $address
 * @property CarbonImmutable $admission_date
 * @property StudentStatus $status
 * @property string|null $photo_path
 * @property string|null $card_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['school_id', 'user_id', 'admission_no', 'first_name', 'last_name', 'gender', 'date_of_birth', 'email', 'national_id', 'blood_group', 'medical_notes', 'address', 'admission_date', 'status'])]
#[Hidden(['national_id_index', 'photo_path', 'card_token'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use Auditable, BelongsToSchool, HasBlindIndexes, HasFactory, HasFullName, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['national_id_index'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'national_id' => 'encrypted',
            'medical_notes' => 'encrypted',
            'address' => 'encrypted',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function blindIndexes(): array
    {
        return ['national_id' => 'national_id_index'];
    }

    protected function normalizeForBlindIndex(string $attribute, string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($value));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['relationship', 'is_primary', 'is_emergency_contact'])
            ->orderByPivot('is_primary', 'desc');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The enrollment in the school's current academic year, if any.
     *
     * @return HasOne<Enrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->whereHas('academicYear', fn (Builder $query) => $query->where('is_current', true));
    }

    /**
     * The student's current hostel stay, if any.
     *
     * @return HasOne<HostelAllocation, $this>
     */
    public function hostelStay(): HasOne
    {
        return $this->hasOne(HostelAllocation::class)->whereNull('ends_on')->latestOfMany('starts_on');
    }
}
