<?php

namespace App\Support;

/**
 * Great-circle distance helpers. Mirrors the `_haversine_km` implementation in
 * the tokenization service so the two agree on what "500 metres away" means.
 */
final class Geo
{
    /** Mean Earth radius in metres. */
    public const EARTH_RADIUS_METERS = 6_371_000.0;

    public static function distanceInMeters(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ): float {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * asin(min(1.0, sqrt($a)));
    }
}
