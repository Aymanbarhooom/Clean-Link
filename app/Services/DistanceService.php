<?php

namespace App\Services\Chat;

class DistanceService
{
    public function calculateKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371;

        $latDifference = deg2rad(
            $lat2 - $lat1
        );

        $lngDifference = deg2rad(
            $lng2 - $lng1
        );

        $a =
            sin($latDifference / 2) ** 2 +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($lngDifference / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return round(
            $earthRadius * $c,
            2
        );
    }
}