<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cheque = 'cheque';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank transfer',
            self::Card => 'Card',
            self::Cheque => 'Cheque',
            self::Online => 'Online',
        };
    }

    /**
     * Cash goes to the cash account; everything else is settled through the bank.
     */
    public function accountCode(): string
    {
        return $this === self::Cash ? '1000' : '1010';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
