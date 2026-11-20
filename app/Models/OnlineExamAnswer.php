<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's answer to one exam question. Saved as the student works (autosave) and
 * marked when the attempt is handed in.
 *
 * @property int $id
 * @property int $school_id
 * @property int $online_exam_attempt_id
 * @property int $online_exam_question_id
 * @property list<string>|string|null $answer
 * @property bool|null $is_correct
 * @property string|null $marks_awarded
 * @property string|null $feedback
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'online_exam_attempt_id', 'online_exam_question_id', 'answer', 'is_correct', 'marks_awarded', 'feedback'])]
class OnlineExamAnswer extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'is_correct' => 'boolean',
            'marks_awarded' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<OnlineExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(OnlineExamAttempt::class, 'online_exam_attempt_id');
    }

    /**
     * @return BelongsTo<OnlineExamQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(OnlineExamQuestion::class, 'online_exam_question_id');
    }
}
