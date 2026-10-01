<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ResponsePersonnel;
use App\Support\Geo;
use Illuminate\Validation\ValidationException;

/**
 * Feature 7: Tanod location lock.
 *
 * A tanod may only go on duty while standing within
 * `sagip.tanod.check_in_radius_meters` of the barangay hall. This service is the
 * single authority on that rule, so every path that could mark a tanod active —
 * their own check-in, their own availability toggle, and an official editing
 * their record — goes through the same check rather than each re-implementing it.
 */
class TanodDutyService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function hallCoordinates(): array
    {
        return [
            'latitude' => (float) config('sagip.hall.latitude'),
            'longitude' => (float) config('sagip.hall.longitude'),
        ];
    }

    public function radiusMeters(): int
    {
        return (int) config('sagip.tanod.check_in_radius_meters', 150);
    }

    public function distanceFromHall(float $latitude, float $longitude): float
    {
        $hall = $this->hallCoordinates();

        return round(Geo::distanceInMeters(
            $hall['latitude'],
            $hall['longitude'],
            $latitude,
            $longitude,
        ), 2);
    }

    public function isWithinHallRadius(float $latitude, float $longitude): bool
    {
        return $this->distanceFromHall($latitude, $longitude) <= $this->radiusMeters();
    }

    /**
     * Whether this responder's duty status is geofenced at all. Only the tanod
     * role is — medics and fire responders are dispatched from wherever they are.
     */
    public function requiresGeofence(ResponsePersonnel $personnel): bool
    {
        return $personnel->user?->role === UserRole::Tanod;
    }

    /**
     * A tanod is considered on duty only while an accepted on-site check-in is
     * recorded against their row.
     */
    public function hasAcceptedCheckIn(ResponsePersonnel $personnel): bool
    {
        if ($personnel->on_duty_at === null) {
            return false;
        }

        if ($personnel->last_check_in_distance_meters === null) {
            return false;
        }

        return (float) $personnel->last_check_in_distance_meters <= $this->radiusMeters();
    }

    /**
     * Record an on-duty check-in.
     *
     * @return array{accepted: bool, distance: float, radius: int}
     *
     * @throws ValidationException when the tanod is outside the hall radius.
     */
    public function checkIn(ResponsePersonnel $personnel, float $latitude, float $longitude): array
    {
        $distance = $this->distanceFromHall($latitude, $longitude);
        $radius = $this->radiusMeters();
        $accepted = $distance <= $radius;

        if (! $accepted) {
            // Flag the attempt before refusing it: a pattern of remote check-in
            // attempts is exactly what an administrator needs to see.
            $this->auditLogger->record(
                action: 'tanod.check_in_rejected',
                subject: $personnel,
                after: [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'distance_meters' => $distance,
                    'radius_meters' => $radius,
                ],
                description: sprintf(
                    '%s tried to go on duty %sm from the barangay hall (limit %sm).',
                    $personnel->name,
                    number_format($distance),
                    number_format($radius),
                ),
                actor: $personnel->user,
            );

            throw ValidationException::withMessages([
                'latitude' => sprintf(
                    'You are %sm from the barangay hall. Check in within %sm of the hall to go on duty.',
                    number_format($distance),
                    number_format($radius),
                ),
            ]);
        }

        $personnel->update([
            'is_available' => true,
            'unavailability_reason' => null,
            'on_duty_at' => now(),
            'last_check_in_latitude' => $latitude,
            'last_check_in_longitude' => $longitude,
            'last_check_in_distance_meters' => $distance,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'last_location_update' => now(),
        ]);

        $this->auditLogger->record(
            action: 'tanod.checked_in',
            subject: $personnel,
            after: [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'distance_meters' => $distance,
            ],
            description: sprintf('%s went on duty %sm from the barangay hall.', $personnel->name, number_format($distance)),
            actor: $personnel->user,
        );

        return ['accepted' => true, 'distance' => $distance, 'radius' => $radius];
    }

    public function checkOut(ResponsePersonnel $personnel, ?string $reason = null): void
    {
        $personnel->update([
            'is_available' => false,
            'unavailability_reason' => $reason,
            'on_duty_at' => null,
            'last_check_in_distance_meters' => null,
        ]);

        $this->auditLogger->record(
            action: 'tanod.checked_out',
            subject: $personnel,
            description: sprintf('%s went off duty.', $personnel->name),
            actor: $personnel->user,
        );
    }

    /**
     * Refuse to mark a geofenced responder active without an accepted check-in,
     * whoever is asking.
     *
     * @throws ValidationException
     */
    public function assertMayBeMarkedAvailable(ResponsePersonnel $personnel, string $errorKey = 'is_available'): void
    {
        if (! $this->requiresGeofence($personnel)) {
            return;
        }

        if ($this->hasAcceptedCheckIn($personnel)) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => sprintf(
                'A tanod can only be marked active by checking in within %sm of the barangay hall.',
                number_format($this->radiusMeters()),
            ),
        ]);
    }
}
