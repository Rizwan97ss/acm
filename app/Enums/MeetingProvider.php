<?php

namespace App\Enums;

/**
 * Where a live class takes place.
 */
enum MeetingProvider: string
{
    case Zoom = 'zoom';
    case GoogleMeet = 'google_meet';
    case Teams = 'teams';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Zoom => 'Zoom',
            self::GoogleMeet => 'Google Meet',
            self::Teams => 'Microsoft Teams',
            self::Other => 'Other',
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
