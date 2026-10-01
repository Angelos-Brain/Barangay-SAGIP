<?php

namespace App\Enums;

/**
 * Feature 10: Personnel Specialization Tags.
 *
 * The first five values are the specialization strings this application has
 * always stored on `response_personnel.specialization`, so existing rows stay
 * valid. `Weather` is new — it gives weather/storm-watch responders a tag of
 * their own while still routing to the classifier's `disaster` category.
 */
enum Specialization: string
{
    case Medical = 'medical';
    case Fire = 'fire';
    case Disaster = 'disaster';
    case PeaceOrder = 'peace_order';
    case GeneralAssistance = 'general_assistance';
    case Weather = 'weather';

    public function label(): string
    {
        return match ($this) {
            self::Medical => 'Medical',
            self::Fire => 'Fire',
            self::Disaster => 'Disaster',
            self::PeaceOrder => 'Peace & Order',
            self::GeneralAssistance => 'General Assistance',
            self::Weather => 'Weather',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Medical => 'red',
            self::Fire => 'orange',
            self::Disaster => 'yellow',
            self::PeaceOrder => 'indigo',
            self::GeneralAssistance => 'gray',
            self::Weather => 'blue',
        };
    }

    /**
     * The staff role that corresponds to this specialization, used when
     * provisioning a login for a responder.
     */
    public function role(): UserRole
    {
        return match ($this) {
            self::Medical => UserRole::Medical,
            self::Fire, self::Disaster => UserRole::FireDisaster,
            self::PeaceOrder => UserRole::PeaceOrder,
            self::GeneralAssistance => UserRole::GeneralAssistant,
            self::Weather => UserRole::Weather,
        };
    }

    /**
     * Incident categories emitted by the tokenization classifier that this
     * specialization is expected to respond to (Feature 8 routing).
     *
     * @return list<string>
     */
    public function incidentCategories(): array
    {
        return match ($this) {
            self::Medical => ['medical'],
            self::Fire => ['fire'],
            self::Disaster, self::Weather => ['disaster'],
            self::PeaceOrder => ['peace_order'],
            self::GeneralAssistance => ['general_assistance'],
        };
    }

    /**
     * Specializations that should be notified about the given incident
     * category.
     *
     * @return list<self>
     */
    public static function forIncidentCategory(?string $category): array
    {
        if ($category === null) {
            return self::cases();
        }

        $matches = array_values(array_filter(
            self::cases(),
            fn (self $specialization) => in_array($category, $specialization->incidentCategories(), true),
        ));

        // An unrecognised category should never silence the alert — fall back
        // to the general-assistance responders.
        return $matches !== [] ? $matches : [self::GeneralAssistance];
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
