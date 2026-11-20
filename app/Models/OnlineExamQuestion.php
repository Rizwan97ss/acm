<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use App\Enums\QuestionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question on one exam: the exam's own copy, so editing the question bank later never
 * changes a paper students have sat. The answer key is encrypted at rest and hidden from
 * serialisation.
 *
 * @property int $id
 * @property int $school_id
 * @property int $online_exam_id
 * @property int|null $bank_question_id
 * @property QuestionType $type
 * @property string $prompt
 * @property list<array{id: string, text: string}>|null $options
 * @property list<string>|null $answer_key
 * @property string $marks
 * @property string $negative_marks
 * @property string|null $explanation
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'online_exam_id', 'bank_question_id', 'type', 'prompt', 'options', 'answer_key', 'marks', 'negative_marks', 'explanation', 'position'])]
#[Hidden(['answer_key'])]
class OnlineExamQuestion extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'answer_key' => 'encrypted:array',
            'marks' => 'decimal:2',
            'negative_marks' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OnlineExam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(OnlineExam::class, 'online_exam_id');
    }
}
