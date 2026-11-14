<?php

namespace App\Enums;

enum AssignmentType: string
{
    case Homework = 'homework';
    case Assignment = 'assignment';
    case Project = 'project';

    public function label(): string
    {
        return match ($this) {
            self::Homework => 'Homework',
            self::Assignment => 'Assignment',
            self::Project => 'Project',
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
