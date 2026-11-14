<?php

namespace App\Enums;

/**
 * Who an announcement or event is meant for. Students and guardians can be narrowed
 * further to a single class.
 */
enum Audience: string
{
    case Everyone = 'everyone';
    case Staff = 'staff';
    case Students = 'students';
    case Guardians = 'guardians';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone',
            self::Staff => 'Staff only',
            self::Students => 'Students',
            self::Guardians => 'Parents & guardians',
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
