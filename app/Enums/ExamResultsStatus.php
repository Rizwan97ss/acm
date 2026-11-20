<?php

namespace App\Enums;

/**
 * Results are marked, then approved (marks locked), then published to students.
 */
enum ExamResultsStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Being marked',
            self::Approved => 'Approved',
            self::Published => 'Published',
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
