<?php

namespace App\Enums;

/**
 * Objective types are marked automatically; descriptive ones by the teacher.
 */
enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case Essay = 'essay';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Single choice',
            self::MultipleChoice => 'Multiple choice',
            self::TrueFalse => 'True or false',
            self::ShortAnswer => 'Short answer',
            self::Essay => 'Essay',
        };
    }

    /**
     * Objective questions have a fixed answer key and are marked automatically.
     */
    public function isObjective(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleChoice, self::TrueFalse], true);
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleChoice], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
