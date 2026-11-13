<?php

namespace App\Enums;

/**
 * ISO-8601 day numbers (Monday = 1 … Sunday = 7).
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return $this->name;
    }

    /**
     * The school days for a `academic.school_days` setting value.
     *
     * @return list<self>
     */
    public static function forSchoolWeek(string $week): array
    {
        return match ($week) {
            'mon-sat' => [self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday, self::Saturday],
            'sun-thu' => [self::Sunday, self::Monday, self::Tuesday, self::Wednesday, self::Thursday],
            'sat-wed' => [self::Saturday, self::Sunday, self::Monday, self::Tuesday, self::Wednesday],
            default => [self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday],
        };
    }
}
