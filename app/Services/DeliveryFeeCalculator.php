<?php

namespace App\Services;

use App\Contracts\DistanceCalculator;
use App\Models\Business;
use App\Support\Geo;
use InvalidArgumentException;

/**
 * Delivery fee by distance (D2, rule 21; G5 assumption): base fee + distance ×
 * the store's per-km price, refused beyond the store's maximum distance.
 */
class DeliveryFeeCalculator
{
    public function __construct(private readonly DistanceCalculator $distance) {}

    /**
     * @return array{distance_km: float, fee_cents: int}
     */
    public function quote(Business $business, mixed $latitude, mixed $longitude): array
    {
        if (! $business->hasCoordinates()) {
            throw new InvalidArgumentException(__('This store has not set its location for delivery yet.'));
        }

        if (! Geo::isValidCoordinate($latitude, $longitude)) {
            throw new InvalidArgumentException(__('Pick the delivery location on the map.'));
        }

        $km = round($this->distance->kilometers(
            (float) $business->latitude,
            (float) $business->longitude,
            (float) $latitude,
            (float) $longitude,
        ), 2);

        $maxKm = (float) $business->settings('delivery_max_km', 0);

        if ($maxKm > 0 && $km > $maxKm) {
            throw new InvalidArgumentException(__('This address is outside the delivery area (:max km).', [
                'max' => rtrim(rtrim(number_format($maxKm, 2), '0'), '.'),
            ]));
        }

        $fee = (int) $business->settings('delivery_base_fee_cents', 0)
            + (int) round($km * (int) $business->settings('delivery_fee_per_km_cents', 0));

        return ['distance_km' => $km, 'fee_cents' => $fee];
    }
}
