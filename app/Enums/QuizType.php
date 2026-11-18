<?php

namespace App\Enums;

/**
 * Low-stakes quizzes or formal tests.
 */
enum QuizType: string
{
    case Quiz = 'quiz';
    case Test = 'test';

    public function label(): string
    {
        return match ($this) {
            self::Quiz => 'Quiz',
            self::Test => 'Test',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
