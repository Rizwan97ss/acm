<?php

namespace App\Enums;

/**
 * The bodies that govern the school, highest first.
 */
enum GoverningBody: string
{
    case Trustees = 'trustees';
    case Executive = 'executive';
    case Advisory = 'advisory';

    public function label(): string
    {
        return match ($this) {
            self::Trustees => 'Board of Trustees',
            self::Executive => 'Executive Committee',
            self::Advisory => 'Advisory Council',
        };
    }

    /**
     * Common positions, highest first; members may also have any other title.
     *
     * @return list<string>
     */
    public static function suggestedPositions(): array
    {
        return ['Chairperson', 'Patron', 'President', 'Vice President', 'Secretary General', 'Secretary', 'Joint Secretary', 'Treasurer', 'Trustee', 'Member', 'Advisor'];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
