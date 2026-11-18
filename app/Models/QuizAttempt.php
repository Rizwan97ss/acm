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
 * A student's attempt at a quiz.
 *
 * @property int $id
 * @property int $school_id
 * @property int $quiz_id
 * @property int $student_id
 * @property int $attempt_no
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $score
 * @property string $max_score
 * @property QuizAttemptStatus $status
 * @property string|null $feedback
 * @property int|null $graded_by
 * @property CarbonImmutable|null $graded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'quiz_id', 'student_id', 'attempt_no', 'started_at', 'submitted_at', 'score', 'max_score', 'status', 'feedback', 'graded_by', 'graded_at'])]
class QuizAttempt extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuizAttemptStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'attempt_no' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<QuizAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }
}
