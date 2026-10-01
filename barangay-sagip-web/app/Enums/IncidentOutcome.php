<?php

namespace App\Enums;

/**
 * Feature 2: post-incident validation.
 *
 * Set by the responder or an official once the response is over. False-alarm
 * outcomes feed the per-account count that flags a resident for admin review;
 * nothing here suspends or blocks an account automatically.
 */
enum IncidentOutcome: string
{
    case Confirmed = 'confirmed';
    case FalseAlarm = 'false_alarm';
    case Test = 'test';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::FalseAlarm => 'False Alarm',
            self::Test => 'Test',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Confirmed => 'green',
            self::FalseAlarm => 'red',
            self::Test => 'gray',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
