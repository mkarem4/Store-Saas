<?php

namespace App\Services\Geo;

use App\Contracts\DistanceCalculator;
use App\Support\Geo;

/**
 * Straight-line distance. Used when no Google Maps key is configured and as a
 * fallback when the Distance Matrix API is unavailable.
 */
class HaversineDistanceCalculator implements DistanceCalculator
{
    public function kilometers(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        return Geo::distanceMeters($fromLat, $fromLng, $toLat, $toLng) / 1000;
    }
}
