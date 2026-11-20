<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\ExamResultsStatus;
use App\Enums\OnlineExamState;
use App\Enums\PublishStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OnlineExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An online examination for a class, one section, or chosen students of a class.
 *
 * `status` says whether the exam is released to students (draft or published); its
 * life stage (scheduled, in progress, completed) follows from its start and end times.
 * Results go through marking, approval (marks locked) and publication separately.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $school_class_id
 * @property int|null $section_id
 * @property int $subject_id
 * @property string $title
 * @property string|null $instructions
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $duration_minutes
 * @property string $total_marks
 * @property string $passing_marks
 * @property int $max_attempts
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property bool $negative_marking
 * @property bool $show_rank
 * @property PublishStatus $status
 * @property ExamResultsStatus $results_status
 * @property int|null $results_approved_by
 * @property CarbonImmutable|null $results_approved_at
 * @property int|null $results_published_by
 * @property CarbonImmutable|null $results_published_at
 * @property CarbonImmutable|null $notified_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'academic_year_id', 'school_class_id', 'section_id', 'subject_id', 'title', 'instructions', 'starts_at', 'ends_at', 'duration_minutes', 'total_marks', 'passing_marks', 'max_attempts', 'shuffle_questions', 'shuffle_options', 'negative_marking', 'show_rank', 'status', 'created_by'])]
class OnlineExam extends Model
{
    /** @use HasFactory<OnlineExamFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * Defaults matching the table, so a new model knows its status before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'results_status' => 'pending',
        'max_attempts' => 1,
        'shuffle_questions' => false,
        'shuffle_options' => false,
        'negative_marking' => false,
        'show_rank' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'total_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
            'max_attempts' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'negative_marking' => 'boolean',
            'show_rank' => 'boolean',
            'status' => PublishStatus::class,
            'results_status' => ExamResultsStatus::class,
            'results_approved_at' => 'datetime',
            'results_published_at' => 'datetime',
            'notified_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<OnlineExamQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(OnlineExamQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<OnlineExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(OnlineExamAttempt::class);
    }

    /**
     * Students the exam is limited to. Empty means the whole class or section.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'online_exam_student');
    }

    public function state(?CarbonImmutable $at = null): OnlineExamState
    {
        $at ??= CarbonImmutable::now();

        return match (true) {
            $this->status === PublishStatus::Draft => OnlineExamState::Draft,
            $this->results_status === ExamResultsStatus::Published => OnlineExamState::ResultsPublished,
            $at->lt($this->starts_at) => OnlineExamState::Scheduled,
            $at->lt($this->ends_at) => OnlineExamState::Active,
            default => OnlineExamState::Completed,
        };
    }

    /**
     * Released to students and inside its exam window.
     */
    public function isOpen(?CarbonImmutable $at = null): bool
    {
        return $this->state($at) === OnlineExamState::Active;
    }

    public function resultsPublished(): bool
    {
        return $this->results_status === ExamResultsStatus::Published;
    }

    /**
     * Questions, marks and settings are frozen once a student has started.
     */
    public function isLocked(): bool
    {
        return $this->attempts()->exists();
    }
}
