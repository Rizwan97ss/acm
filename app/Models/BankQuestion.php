<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable question for a subject (optionally one class), copied into exams.
 *
 * @property int $id
 * @property int $school_id
 * @property int $subject_id
 * @property int|null $school_class_id
 * @property QuestionType $type
 * @property QuestionDifficulty $difficulty
 * @property string $prompt
 * @property list<array{id: string, text: string}>|null $options
 * @property list<string>|null $answer_key
 * @property string $marks
 * @property string $negative_marks
 * @property string|null $explanation
 * @property string|null $topic
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'subject_id', 'school_class_id', 'type', 'difficulty', 'prompt', 'options', 'answer_key', 'marks', 'negative_marks', 'explanation', 'topic', 'created_by'])]
#[Hidden(['answer_key'])]
class BankQuestion extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => QuestionDifficulty::class,
            'options' => 'array',
            'answer_key' => 'encrypted:array',
            'marks' => 'decimal:2',
            'negative_marks' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
