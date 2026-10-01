<?php

namespace App\Enums;

/**
 * Feature 2: why the resident pressed SOS.
 *
 * Chosen on the device before the SOS is dispatched and mapped onto the
 * classifier's incident categories, so an SOS is routed to the same
 * specialization tags (Feature 10) as an ordinary report. `Other` carries no
 * category and therefore alerts every specialization.
 */
enum SosReason: string
{
    case Medical = 'medical';
    case Fire = 'fire';
    case CrimePeaceOrder = 'crime_peace_order';
    case WeatherFlood = 'weather_flood';
    case MissingPerson = 'missing_person';
    case Accident = 'accident';
    case Other = 'other';

    /** Maximum length of the free-text explanation required for `Other`. */
    public const OTHER_MAX_LENGTH = 100;

    public function label(): string
    {
        return match ($this) {
            self::Medical => 'Medical',
            self::Fire => 'Fire',
            self::CrimePeaceOrder => 'Crime / Peace & Order',
            self::WeatherFlood => 'Weather / Flood',
            self::MissingPerson => 'Missing Person',
            self::Accident => 'Accident',
            self::Other => 'Other',
        };
    }

    /**
     * The incident category this reason routes to, or null to alert every
     * specialization.
     */
    public function incidentCategory(): ?string
    {
        return match ($this) {
            self::Medical, self::Accident => 'medical',
            self::Fire => 'fire',
            self::CrimePeaceOrder, self::MissingPerson => 'peace_order',
            self::WeatherFlood => 'disaster',
            self::Other => null,
        };
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
