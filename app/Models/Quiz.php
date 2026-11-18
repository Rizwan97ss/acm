<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\PublishStatus;
use App\Enums\QuizType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quiz or test for a class (optionally one section). Objective questions are marked
 * automatically; descriptive ones wait for the teacher.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $school_class_id
 * @property int|null $section_id
 * @property int $subject_id
 * @property int|null $course_id
 * @property QuizType $type
 * @property string $title
 * @property string|null $instructions
 * @property CarbonImmutable|null $opens_at
 * @property CarbonImmutable|null $closes_at
 * @property int|null $time_limit_minutes
 * @property int $max_attempts
 * @property bool $shuffle_questions
 * @property bool $show_results
 * @property PublishStatus $status
 * @property CarbonImmutable|null $notified_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'academic_year_id', 'school_class_id', 'section_id', 'subject_id', 'course_id', 'type', 'title', 'instructions', 'opens_at', 'closes_at', 'time_limit_minutes', 'max_attempts', 'shuffle_questions', 'show_results', 'status', 'notified_at', 'created_by'])]
class Quiz extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuizType::class,
            'status' => PublishStatus::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'notified_at' => 'datetime',
            'time_limit_minutes' => 'integer',
            'max_attempts' => 'integer',
            'shuffle_questions' => 'boolean',
            'show_results' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isOpen(?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now();

        return $this->status === PublishStatus::Published
            && ($this->opens_at === null || $this->opens_at->lte($at))
            && ($this->closes_at === null || $this->closes_at->gt($at));
    }
}
