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
 * One question. `options` holds the choices ({id, text}); `answer_key` the correct option
 * ids, true/false, or accepted short answers. Essays have no key.
 *
 * @property int $id
 * @property int $school_id
 * @property int $quiz_id
 * @property QuestionType $type
 * @property string $prompt
 * @property list<array{id: string, text: string}>|null $options
 * @property list<string>|null $answer_key
 * @property string $marks
 * @property string|null $explanation
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'quiz_id', 'type', 'prompt', 'options', 'answer_key', 'marks', 'explanation', 'position'])]
#[Hidden(['answer_key'])]
class QuizQuestion extends Model
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
            'answer_key' => 'array',
            'marks' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
