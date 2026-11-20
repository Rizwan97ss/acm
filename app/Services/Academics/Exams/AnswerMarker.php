<?php

namespace App\Services\Academics\Exams;

use App\Enums\QuestionType;
use App\Models\OnlineExamQuestion;
use Illuminate\Support\Str;

/**
 * Cleans what a student sent and marks objective questions.
 *
 * Anything that is not a real option of the question (a tampered request) is dropped,
 * so a student cannot submit option ids from another question or invent values.
 *
 * Single choice and true/false are right or wrong. Multiple choice is all-or-nothing:
 * every correct option and no wrong one. Short answers match the accepted answers
 * ignoring case and surrounding spaces; with no accepted answers, and for essays, the
 * teacher marks by hand. With negative marking on, a wrong objective answer loses the
 * question's negative marks; an unanswered question never does.
 */
class AnswerMarker
{
    public const int SHORT_ANSWER_LIMIT = 500;

    public const int ESSAY_LIMIT = 20000;

    /**
     * @return list<string>|string|null
     */
    public function normalise(OnlineExamQuestion $question, mixed $given): array|string|null
    {
        if ($given === null || $given === '' || $given === []) {
            return null;
        }

        $optionIds = array_column($question->options ?? [], 'id');

        return match ($question->type) {
            QuestionType::SingleChoice => is_scalar($given) && in_array((string) $given, $optionIds, true) ? (string) $given : null,
            QuestionType::MultipleChoice => $this->choices($given, $optionIds),
            QuestionType::TrueFalse => is_scalar($given) && in_array((string) $given, ['true', 'false'], true) ? (string) $given : null,
            QuestionType::ShortAnswer => $this->text($given, self::SHORT_ANSWER_LIMIT),
            QuestionType::Essay => $this->text($given, self::ESSAY_LIMIT),
        };
    }

    /**
     * @param  list<string>|string|null  $given  an answer already passed through normalise()
     * @return array{0: bool|null, 1: float|null} [correct?, marks]; [null, null] when the teacher must mark
     */
    public function mark(OnlineExamQuestion $question, array|string|null $given, bool $negativeMarking): array
    {
        if ($given === null) {
            return [false, 0.0];
        }

        $key = array_map('strval', $question->answer_key ?? []);

        $correct = match ($question->type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse => is_string($given) && in_array($given, $key, true),
            QuestionType::MultipleChoice => is_array($given) && $key !== [] && array_diff($given, $key) === [] && array_diff($key, $given) === [],
            QuestionType::ShortAnswer => $key === [] ? null : (is_string($given) && in_array($this->fold($given), array_map($this->fold(...), $key), true)),
            QuestionType::Essay => null,
        };

        if ($correct === null) {
            return [null, null];
        }

        if ($correct) {
            return [true, (float) $question->marks];
        }

        return [false, $negativeMarking && $question->type->isObjective() ? -1 * (float) $question->negative_marks : 0.0];
    }

    /**
     * The lowest and highest marks a teacher may award for a question.
     *
     * @return array{0: float, 1: float}
     */
    public function range(OnlineExamQuestion $question, bool $negativeMarking): array
    {
        $floor = $negativeMarking && $question->type->isObjective() ? -1 * (float) $question->negative_marks : 0.0;

        return [$floor, (float) $question->marks];
    }

    /**
     * @param  list<string>  $optionIds
     * @return list<string>|null
     */
    private function choices(mixed $given, array $optionIds): ?array
    {
        $values = array_filter((array) $given, fn (mixed $value): bool => is_scalar($value) && in_array((string) $value, $optionIds, true));
        $values = array_values(array_unique(array_map('strval', $values)));
        sort($values);

        return $values === [] ? null : $values;
    }

    private function text(mixed $given, int $limit): ?string
    {
        if (! is_scalar($given)) {
            return null;
        }

        $text = Str::limit(trim((string) $given), $limit, '');

        return $text === '' ? null : $text;
    }

    private function fold(string $value): string
    {
        return Str::lower(preg_replace('/\s+/', ' ', trim($value)) ?? '');
    }
}
