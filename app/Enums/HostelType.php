<?php

namespace App\Enums;

/**
 * Who a hostel houses.
 */
enum HostelType: string
{
    case Boys = 'boys';
    case Girls = 'girls';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Boys => 'Boys',
            self::Girls => 'Girls',
            self::Mixed => 'Mixed',
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
