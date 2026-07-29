<?php

namespace App\Support;

final class Geofence
{
    private const EARTH_RADIUS_METERS = 6371000;

    public static function distanceInMeters(
        float $latitude,
        float $longitude,
        float $targetLatitude,
        float $targetLongitude,
    ): float {
        $latitudeDelta = deg2rad($targetLatitude - $latitude);
        $longitudeDelta = deg2rad($targetLongitude - $longitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude))
            * cos(deg2rad($targetLatitude))
            * sin($longitudeDelta / 2) ** 2;

        return round(
            self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a)),
            2,
        );
    }
}
