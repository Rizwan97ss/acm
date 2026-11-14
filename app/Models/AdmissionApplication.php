<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Concerns\HasFullName;
use App\Enums\AdmissionStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use Carbon\CarbonImmutable;
use Database\Factories\AdmissionApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $school_id
 * @property string $application_no
 * @property int $academic_year_id
 * @property int $school_class_id
 * @property string $first_name
 * @property string $last_name
 * @property-read string $full_name
 * @property Gender $gender
 * @property CarbonImmutable $date_of_birth
 * @property string|null $previous_school
 * @property string $guardian_name
 * @property GuardianRelationship $guardian_relationship
 * @property string $guardian_phone
 * @property string|null $guardian_email
 * @property string|null $notes
 * @property AdmissionStatus $status
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $decision_notes
 * @property int|null $student_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'application_no', 'academic_year_id', 'school_class_id', 'first_name', 'last_name', 'gender', 'date_of_birth', 'previous_school', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email', 'notes', 'status', 'created_by'])]
class AdmissionApplication extends Model
{
    /** @use HasFactory<AdmissionApplicationFactory> */
    use Auditable, BelongsToSchool, HasFactory, HasFullName;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'guardian_relationship' => GuardianRelationship::class,
            'date_of_birth' => 'date',
            'guardian_phone' => 'encrypted',
            'status' => AdmissionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
