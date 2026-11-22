<?php

namespace App\Contracts;

interface DistanceCalculator
{
    /**
     * Distance in kilometers between two coordinates (driving distance when
     * the implementation supports it).
     */
    public function kilometers(float $fromLat, float $fromLng, float $toLat, float $toLng): float;
}
