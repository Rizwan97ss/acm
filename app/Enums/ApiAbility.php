<?php

namespace App\Enums;

/**
 * Scopes an API token can carry. A scope only opens a group of endpoints; the token
 * owner's RBAC permissions and portal restrictions still apply on every request.
 */
enum ApiAbility: string
{
    case People = 'people:read';
    case Academics = 'academics:read';
    case Finance = 'finance:read';
    case Communication = 'communication:read';

    public function label(): string
    {
        return match ($this) {
            self::People => 'Students',
            self::Academics => 'Timetable and attendance',
            self::Finance => 'Invoices',
            self::Communication => 'Announcements, events and notifications',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[] = ['value' => $case->value, 'label' => $case->label()];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
