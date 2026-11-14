<?php

namespace App\Enums;

enum FeeFrequency: string
{
    case Once = 'once';
    case Monthly = 'monthly';
    case Termly = 'termly';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'One-time',
            self::Monthly => 'Monthly',
            self::Termly => 'Per term',
            self::Annual => 'Annual',
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
