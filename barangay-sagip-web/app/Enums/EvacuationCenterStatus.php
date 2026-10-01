<?php

namespace App\Enums;

/**
 * Feature 4: Evacuation Center Management.
 */
enum EvacuationCenterStatus: string
{
    case Open = 'open';
    case Standby = 'standby';
    case Full = 'full';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Standby => 'On standby',
            self::Full => 'Full',
            self::Closed => 'Closed',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Open => 'green',
            self::Standby => 'blue',
            self::Full => 'orange',
            self::Closed => 'gray',
        };
    }

    /**
     * Whether the centre can still take evacuees right now.
     */
    public function acceptsEvacuees(): bool
    {
        return $this === self::Open;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            self::values(),
            array_map(fn (self $case) => $case->label(), self::cases()),
        );
    }
}
