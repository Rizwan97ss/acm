<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\Weekday;
use Carbon\CarbonImmutable;
use Database\Factories\TimetableEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $section_id
 * @property Weekday $day_of_week
 * @property int $period_id
 * @property int $subject_id
 * @property int|null $teacher_id
 * @property string|null $room
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'academic_year_id', 'section_id', 'day_of_week', 'period_id', 'subject_id', 'teacher_id', 'room'])]
class TimetableEntry extends Model
{
    /** @use HasFactory<TimetableEntryFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => Weekday::class,
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
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Period, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
