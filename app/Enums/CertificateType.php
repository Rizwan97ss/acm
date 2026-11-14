<?php

namespace App\Enums;

enum CertificateType: string
{
    case Bonafide = 'bonafide';
    case Character = 'character';
    case Transfer = 'transfer';
    case Achievement = 'achievement';

    public function label(): string
    {
        return match ($this) {
            self::Bonafide => 'Bonafide (enrolment)',
            self::Character => 'Character',
            self::Transfer => 'Transfer / leaving',
            self::Achievement => 'Achievement',
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
