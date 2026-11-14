<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\ExamPaperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One subject sat by one class within an exam.
 *
 * @property int $id
 * @property int $school_id
 * @property int $exam_id
 * @property int $school_class_id
 * @property int $subject_id
 * @property CarbonImmutable|null $held_on
 * @property string $max_marks
 * @property string $pass_marks
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'exam_id', 'school_class_id', 'subject_id', 'held_on', 'max_marks', 'pass_marks'])]
class ExamPaper extends Model
{
    /** @use HasFactory<ExamPaperFactory> */
    use BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'max_marks' => 'decimal:2',
            'pass_marks' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<Mark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }
}
