<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Services;

/** Haversine meter. Murni, bisa di-unit-test. */
final class GeoService
{
    public function meters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(sqrt($a));
    }

    public function withinRadius(float $lat1, float $lng1, float $lat2, float $lng2, int $radiusM): bool
    {
        return $this->meters($lat1, $lng1, $lat2, $lng2) <= $radiusM;
    }
}
