<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use App\Enums\QuizAttemptStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sitting of an exam by one student. The deadline is fixed on the server when the
 * attempt starts; `layout` stores the question order (and option order) that student saw.
 *
 * @property int $id
 * @property int $school_id
 * @property int $online_exam_id
 * @property int $student_id
 * @property int $attempt_no
 * @property QuizAttemptStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $deadline_at
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $submitted_by
 * @property list<array{question: int, options: list<string>}> $layout
 * @property string|null $score
 * @property string $max_score
 * @property string|null $percentage
 * @property string|null $grade
 * @property bool|null $passed
 * @property string|null $feedback
 * @property int|null $graded_by
 * @property CarbonImmutable|null $graded_at
 * @property string|null $ip_address
 * @property CarbonImmutable|null $last_saved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'online_exam_id', 'student_id', 'attempt_no', 'status', 'started_at', 'deadline_at', 'layout', 'max_score', 'ip_address'])]
class OnlineExamAttempt extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuizAttemptStatus::class,
            'attempt_no' => 'integer',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'layout' => 'array',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OnlineExam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(OnlineExam::class, 'online_exam_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<OnlineExamAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(OnlineExamAnswer::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === QuizAttemptStatus::InProgress;
    }
}
