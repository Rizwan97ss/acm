<?php

namespace App\Services\Finance;

/**
 * Converts between the major units people type ("1500.50") and the integer minor
 * units (cents) stored in the database, so arithmetic never touches floats.
 */
final class Money
{
    public static function toCents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toMajor(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * A percentage of an amount, rounded to the nearest cent.
     */
    public static function percentOf(int $cents, float $percent): int
    {
        return (int) round($cents * $percent / 100);
    }
}
