<?php

namespace App\Enums;

/**
 * Where an online exam is in its life, worked out from its status and times.
 */
enum OnlineExamState: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case ResultsPublished = 'results_published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Active => 'In progress',
            self::Completed => 'Completed',
            self::ResultsPublished => 'Results published',
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
